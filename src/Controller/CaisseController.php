<?php

namespace App\Controller;

use App\Entity\FactureGlobale;
use App\Entity\LigneFacture;
use App\Entity\MouvementStock;
use App\Repository\ActeMedicalRepository;
use App\Repository\ConsultationRepository;
use App\Repository\FactureGlobaleRepository;
use App\Repository\PartenaireRepository;
use App\Repository\PatientRepository;
use App\Repository\ProduitPharmaceutiqueRepository;
use App\Repository\PrescriptionExamenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/caisse')]
#[IsGranted('ROLE_CAISSIER')]
class CaisseController extends AbstractController
{
    public function __construct(
        private FactureGlobaleRepository        $factureRepo,
        private PatientRepository               $patientRepo,
        private ActeMedicalRepository           $acteRepo,
        private ProduitPharmaceutiqueRepository $produitRepo,
        private PartenaireRepository            $partenaireRepo,
        private ConsultationRepository          $consultationRepo,
        private PrescriptionExamenRepository    $prescriptionRepo,
        private EntityManagerInterface          $em,
        private PaginatorInterface              $paginator
    ) {}

    #[Route('/', name: 'app_caisse_index')]
    public function index(Request $request): Response
    {
        $search = $request->query->get('search', '');
        $date   = $request->query->get('date', date('Y-m-d'));
        $statut = $request->query->get('statut', '');

        $qb         = $this->factureRepo->getFilteredQueryBuilder($search, $date, $statut);
        $pagination = $this->paginator->paginate($qb, $request->query->getInt('page', 1), 25);

        // Stats du jour
        $dateJour    = new \DateTime($date);
        $dateJourEnd = (clone $dateJour)->setTime(23, 59, 59);
        $statsJour   = [
            'total_encaisse' => $this->factureRepo->sumByPeriod($dateJour, $dateJourEnd, 'paye'),
            'nb_factures'    => $this->factureRepo->countByPeriod($dateJour, $dateJourEnd),
            'nb_payees'      => $this->factureRepo->countByPeriodStatut($dateJour, $dateJourEnd, 'paye'),
        ];

        return $this->render('caisse/index.html.twig', [
            'pagination'    => $pagination,
            'search'        => $search,
            'date_filter'   => $date,
            'statut_filter' => $statut,
            'stats_jour'    => $statsJour,
            'currency'      => $_ENV['CURRENCY'] ?? 'FCFA',
        ]);
    }

    #[Route('/nouvelle-facture', name: 'app_caisse_new', methods: ['GET', 'POST'])]
    public function newFacture(Request $request): Response
    {
        $partenaires   = $this->partenaireRepo->findBy(['actif' => true], ['nom' => 'ASC']);
        // Correction : utiliser 'designation' comme champ de tri (pas 'libelle')
        $actesMedicaux = $this->acteRepo->findBy(['actif' => true], ['designation' => 'ASC']);

        if ($request->isMethod('POST')) {
            $data    = $request->request->all();
            $patient = $this->patientRepo->find($data['patient_id'] ?? 0);

            if (!$patient) {
                return $this->json(['error' => 'Patient invalide'], 400);
            }

            $facture = new FactureGlobale();
            $facture->setPatient($patient);
            $facture->setCaissier($this->getUser());

            // Partenaire / assurance
            if (!empty($data['partenaire_id'])) {
                $partenaire = $this->partenaireRepo->find($data['partenaire_id']);
                if ($partenaire) {
                    $facture->setPartenaire($partenaire);
                    $facture->setTauxAssurance((float)$partenaire->getTauxPriseEnCharge());
                }
            }

            $facture->setNumero($this->generateNumeroFacture());

            if (!empty($data['mode_paiement'])) {
                $facture->setModePaiement($data['mode_paiement']);
            }

            $this->em->persist($facture);

            // Lignes de facturation
            $lignesData = json_decode($data['lignes_json'] ?? '[]', true);
            $hasProduit = false;

            foreach ($lignesData as $ligneData) {
                $ligne = new LigneFacture();
                $ligne->setFactureGlobale($facture);
                $ligne->setDesignation($ligneData['designation'] ?? '');
                $ligne->setQuantite((int)($ligneData['quantite'] ?? 1));
                $ligne->setPrixUnitaire((float)($ligneData['prix_unitaire'] ?? 0));

                $sousTotal = $ligne->getQuantite() * $ligne->getPrixUnitaire();

                // Remise éventuelle
                if (!empty($ligneData['taux_remise'])) {
                    $remise    = (float)$ligneData['taux_remise'];
                    $sousTotal = $sousTotal * (1 - $remise / 100);
                }
                $ligne->setSousTotal($sousTotal);

                // Type de ligne
                if (!empty($ligneData['acte_id'])) {
                    $acte = $this->acteRepo->find($ligneData['acte_id']);
                    if ($acte) {
                        $ligne->setActeMedical($acte);
                        $ligne->setTypeLigne('acte');
                    }
                }

                if (!empty($ligneData['produit_id'])) {
                    $produit = $this->produitRepo->find($ligneData['produit_id']);
                    if ($produit) {
                        if (!$produit->isDisponible($ligne->getQuantite())) {
                            return $this->json([
                                'error' => "Stock insuffisant pour: {$produit->getDesignation()}. "
                                         . "Stock actuel: {$produit->getStockDisponible()}",
                            ], 400);
                        }
                        $ligne->setProduit($produit);
                        $ligne->setTypeLigne('produit');
                        $hasProduit = true;
                    }
                }

                $this->em->persist($ligne);
                $facture->addLigne($ligne);
            }

            // Calculer totaux (méthode dans l'entité)
            $facture->recalculerTotaux();

            // Paiement
            $montantRecu   = (float)($data['montant_recu'] ?? 0);
            $monnaieRendue = max(0, $montantRecu - $facture->getPartPatient());

            if ($montantRecu >= $facture->getPartPatient() && $facture->getPartPatient() > 0) {
                $facture->setStatut(FactureGlobale::STATUT_PAYE);

                // Décrémenter stocks si produits pharmaceutiques
                if ($hasProduit) {
                    foreach ($facture->getLignes() as $ligne) {
                        if ($ligne->getProduit()) {
                            $produit    = $ligne->getProduit();
                            $stockApres = $produit->getStockDisponible() - $ligne->getQuantite();
                            $produit->decrementerStock($ligne->getQuantite());

                            $mouvement = new MouvementStock();
                            $mouvement->setProduit($produit);
                            $mouvement->setUser($this->getUser());
                            $mouvement->setType('sortie');
                            $mouvement->setQuantite($ligne->getQuantite());
                            $mouvement->setStockApres($produit->getStockDisponible());
                            $mouvement->setNotes("Vente - Facture {$facture->getNumero()}");
                            $this->em->persist($mouvement);
                        }
                    }
                }
            } elseif ($montantRecu > 0) {
                $facture->setStatut(FactureGlobale::STATUT_PARTIEL);
            } else {
                $facture->setStatut(FactureGlobale::STATUT_EN_ATTENTE);
            }

            $facture->setNotes($data['notes'] ?? null);
            $this->em->flush();

            return $this->json([
                'success'    => true,
                'facture_id' => $facture->getId(),
                'numero'     => $facture->getNumero(),
                'redirect'   => $this->generateUrl('app_caisse_recu', ['id' => $facture->getId()]),
                'monnaie'    => $monnaieRendue,
            ]);
        }

        return $this->render('caisse/new.html.twig', [
            'partenaires'   => $partenaires,
            'actes_medicaux' => $actesMedicaux,
            'currency'      => $_ENV['CURRENCY'] ?? 'FCFA',
        ]);
    }

    #[Route('/facture/{id}', name: 'app_caisse_facture_show')]
    public function show(FactureGlobale $facture): Response
    {
        return $this->render('caisse/show.html.twig', [
            'facture'  => $facture,
            'currency' => $_ENV['CURRENCY'] ?? 'FCFA',
        ]);
    }

    #[Route('/facture/{id}/recu', name: 'app_caisse_recu')]
    public function recu(FactureGlobale $facture): Response
    {
        return $this->render('caisse/recu.html.twig', [
            'facture'  => $facture,
            'currency' => $_ENV['CURRENCY'] ?? 'FCFA',
        ]);
    }

    #[Route('/facture/{id}/imprimer', name: 'app_caisse_imprimer')]
    public function imprimer(FactureGlobale $facture): Response
    {
        return $this->render('caisse/imprimer.html.twig', [
            'facture'  => $facture,
            'currency' => $_ENV['CURRENCY'] ?? 'FCFA',
        ]);
    }

    #[Route('/situation-journaliere', name: 'app_caisse_situation_journaliere')]
    public function situationJournaliere(Request $request): Response
    {
        $date     = $request->query->get('date', date('Y-m-d'));
        $caissier = $this->getUser();

        if ($this->isGranted('ROLE_ADMIN')) {
            $caissier = null;
        }

        $dateJour    = new \DateTime($date);
        $dateJourEnd = (clone $dateJour)->setTime(23, 59, 59);

        $factures = $this->factureRepo->findForSituationJournaliere($dateJour, $dateJourEnd, $caissier);
        $stats    = $this->factureRepo->getStatsJournalieres($dateJour, $dateJourEnd, $caissier);

        return $this->render('caisse/situation_journaliere.html.twig', [
            'factures'  => $factures,
            'stats'     => $stats,
            'date'      => $dateJour,
            'caissier'  => $caissier,
            'currency'  => $_ENV['CURRENCY'] ?? 'FCFA',
        ]);
    }

    #[Route('/api/produit/{id}/stock', name: 'api_produit_stock')]
    public function apiProduitStock(int $id): JsonResponse
    {
        $produit = $this->produitRepo->find($id);
        if (!$produit) {
            return $this->json(['error' => 'Produit non trouvé'], 404);
        }

        return $this->json([
            'id'         => $produit->getId(),
            'nom'        => $produit->getDesignation(),
            'prix'       => (float)$produit->getPrixVente(),
            'stock'      => $produit->getStockDisponible(),
            'disponible' => $produit->isDisponible(),
            'statut'     => $produit->getStatutStock(),
        ]);
    }

    #[Route('/api/prescription/{consultationId}', name: 'api_prescriptions_consultation')]
    public function apiPrescriptions(int $consultationId): JsonResponse
    {
        $consultation = $this->consultationRepo->find($consultationId);
        if (!$consultation) {
            return $this->json([]);
        }

        $prescriptions = $this->prescriptionRepo->findBy(['consultation' => $consultation]);
        $result = [];
        foreach ($prescriptions as $p) {
            $acte     = $p->getActeMedical();
            if (!$acte) continue;
            $result[] = [
                'id'          => $p->getId(),
                'acte_id'     => $acte->getId(),
                'designation' => $acte->getDesignation(),  // ← correction
                'prix_normal' => (float)$acte->getPrixNormal(),
                'prix_pec'    => (float)$acte->getPrixPrisEnCharge(),
                'statut'      => $p->getStatut(),
            ];
        }
        return $this->json($result);
    }

    private function generateNumeroFacture(): string
    {
        $year       = date('Y');
        $month      = date('m');
        $lastFacture = $this->factureRepo->findOneBy([], ['id' => 'DESC']);
        $nextId     = $lastFacture ? $lastFacture->getId() + 1 : 1;
        return sprintf('F-%s%s-%05d', $year, $month, $nextId);
    }
}
