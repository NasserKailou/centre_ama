<?php

namespace App\Controller;

use App\Entity\Partenaire;
use App\Repository\FactureGlobaleRepository;
use App\Repository\PartenaireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/partenaires')]
#[IsGranted('ROLE_ADMIN')]
class PartenaireController extends AbstractController
{
    public function __construct(
        private PartenaireRepository     $partenaireRepo,
        private FactureGlobaleRepository $factureRepo,
        private EntityManagerInterface   $em,
        private PaginatorInterface       $paginator
    ) {}

    #[Route('/', name: 'app_partenaire_index')]
    public function index(Request $request): Response
    {
        // Liste paginée
        $pagination = $this->paginator->paginate(
            $this->partenaireRepo->createQueryBuilder('p')->orderBy('p.nom', 'ASC'),
            $request->query->getInt('page', 1),
            20
        );

        // Pour la vue on a aussi besoin de TOUTE la liste (pour les selects)
        $partenaires = $this->partenaireRepo->findActifs();

        // Stats globales
        $stats    = $this->partenaireRepo->getStatsGlobales();
        $currency = $_ENV['CURRENCY'] ?? 'FCFA';

        return $this->render('partenaire/index.html.twig', [
            'pagination'  => $pagination,
            'partenaires' => $partenaires,
            'stats'       => $stats,
            'currency'    => $currency,
        ]);
    }

    #[Route('/nouveau', name: 'app_partenaire_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $partenaire = new Partenaire();

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $this->hydratePartenaire($partenaire, $data);
            $this->em->persist($partenaire);
            $this->em->flush();

            $this->addFlash('success', "Partenaire '{$partenaire->getNom()}' créé.");
            return $this->redirectToRoute('app_partenaire_index');
        }

        return $this->render('partenaire/new.html.twig', ['partenaire' => $partenaire]);
    }

    #[Route('/{id}', name: 'app_partenaire_show', requirements: ['id' => '\d+'])]
    public function show(Partenaire $partenaire, Request $request): Response
    {
        $dateDebut = $request->query->get('date_debut', date('Y-m-01'));
        $dateFin   = $request->query->get('date_fin',   date('Y-m-t'));

        $factures = $this->factureRepo->findByPartenairePeriode(
            $partenaire,
            new \DateTime($dateDebut),
            new \DateTime($dateFin . ' 23:59:59')
        );

        $stats = $this->factureRepo->getStatsPartenaire(
            $partenaire,
            new \DateTime($dateDebut),
            new \DateTime($dateFin . ' 23:59:59')
        );

        return $this->render('partenaire/show.html.twig', [
            'partenaire' => $partenaire,
            'factures'   => $factures,
            'stats'      => $stats,
            'date_debut' => $dateDebut,
            'date_fin'   => $dateFin,
            'currency'   => $_ENV['CURRENCY'] ?? 'FCFA',
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_partenaire_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Partenaire $partenaire): Response
    {
        if ($request->isMethod('POST')) {
            $this->hydratePartenaire($partenaire, $request->request->all());
            $this->em->flush();
            $this->addFlash('success', 'Partenaire mis à jour.');
            return $this->redirectToRoute('app_partenaire_show', ['id' => $partenaire->getId()]);
        }

        return $this->render('partenaire/edit.html.twig', ['partenaire' => $partenaire]);
    }

    #[Route('/{id}/situation', name: 'app_partenaire_situation', requirements: ['id' => '\d+'])]
    public function situation(Partenaire $partenaire, Request $request): Response
    {
        $dateDebut = $request->query->get('date_debut', date('Y-m-01'));
        $dateFin   = $request->query->get('date_fin',   date('Y-m-t'));

        $factures = $this->factureRepo->findByPartenairePeriode(
            $partenaire,
            new \DateTime($dateDebut),
            new \DateTime($dateFin . ' 23:59:59')
        );

        $stats = $this->factureRepo->getStatsPartenaire(
            $partenaire,
            new \DateTime($dateDebut),
            new \DateTime($dateFin . ' 23:59:59')
        );

        return $this->render('partenaire/situation.html.twig', [
            'partenaire' => $partenaire,
            'factures'   => $factures,
            'stats'      => $stats,
            'date_debut' => $dateDebut,
            'date_fin'   => $dateFin,
            'currency'   => $_ENV['CURRENCY'] ?? 'FCFA',
        ]);
    }

    // ─── helper ───────────────────────────────────────────────────────────
    private function hydratePartenaire(Partenaire $partenaire, array $data): void
    {
        if (!empty($data['nom']))  $partenaire->setNom($data['nom']);
        if (!empty($data['type'])) $partenaire->setType($data['type']);
        if (isset($data['contact']))          $partenaire->setContact($data['contact'] ?: null);
        if (isset($data['telephone']))        $partenaire->setTelephone($data['telephone'] ?: null);
        if (isset($data['email']))            $partenaire->setEmail($data['email'] ?: null);
        if (isset($data['adresse']))          $partenaire->setAdresse($data['adresse'] ?: null);
        if (isset($data['description']))      $partenaire->setDescription($data['description'] ?: null);
        if (isset($data['numero_contrat']))   $partenaire->setNumeroContrat($data['numero_contrat'] ?: null);
        if (isset($data['taux_prise_en_charge'])) {
            $partenaire->setTauxPriseEnCharge((float)$data['taux_prise_en_charge']);
        }
        $partenaire->setActif(isset($data['actif']) && $data['actif'] === '1');
    }
}
