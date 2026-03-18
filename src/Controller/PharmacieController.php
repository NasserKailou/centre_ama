<?php

namespace App\Controller;

use App\Entity\MouvementStock;
use App\Entity\ProduitPharmaceutique;
use App\Repository\MouvementStockRepository;
use App\Repository\ProduitPharmaceutiqueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pharmacie')]
#[IsGranted('ROLE_USER')]
class PharmacieController extends AbstractController
{
    public function __construct(
        private ProduitPharmaceutiqueRepository $produitRepo,
        private MouvementStockRepository $mouvementRepo,
        private EntityManagerInterface $em,
        private PaginatorInterface $paginator
    ) {}

    #[Route('/', name: 'app_pharmacie_index')]
    public function index(Request $request): Response
    {
        $search = $request->query->get('search', '');
        $statut = $request->query->get('statut', '');
        $categorie = $request->query->get('categorie', '');

        $qb = $this->produitRepo->getFilteredQueryBuilder($search, $statut, $categorie);
        $pagination = $this->paginator->paginate($qb, $request->query->getInt('page', 1), 25);

        $stats = [
            'total' => $this->produitRepo->count(['actif' => true]),
            'ruptures' => $this->produitRepo->countRuptures(),
            'alertes' => $this->produitRepo->countAlertes(),
        ];

        return $this->render('pharmacie/index.html.twig', [
            'pagination' => $pagination,
            'search' => $search,
            'statut_filter' => $statut,
            'categorie_filter' => $categorie,
            'stats' => $stats,
        ]);
    }

    #[Route('/nouveau', name: 'app_pharmacie_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request): Response
    {
        $produit = new ProduitPharmaceutique();

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $this->hydrateProduit($produit, $data);
            $this->em->persist($produit);
            $this->em->flush();

            $this->addFlash('success', "Produit '{$produit->getNom()}' créé avec succès.");
            return $this->redirectToRoute('app_pharmacie_index');
        }

        return $this->render('pharmacie/new.html.twig', ['produit' => $produit]);
    }

    #[Route('/{id}', name: 'app_pharmacie_show')]
    public function show(ProduitPharmaceutique $produit): Response
    {
        $mouvements = $this->mouvementRepo->findBy(
            ['produit' => $produit],
            ['createdAt' => 'DESC'],
            30
        );

        return $this->render('pharmacie/show.html.twig', [
            'produit' => $produit,
            'mouvements' => $mouvements,
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_pharmacie_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, ProduitPharmaceutique $produit): Response
    {
        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $this->hydrateProduit($produit, $data);
            $this->em->flush();

            $this->addFlash('success', 'Produit mis à jour avec succès.');
            return $this->redirectToRoute('app_pharmacie_show', ['id' => $produit->getId()]);
        }

        return $this->render('pharmacie/edit.html.twig', ['produit' => $produit]);
    }

    #[Route('/{id}/reapprovisionner', name: 'app_pharmacie_reappro', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function reapprovisionner(Request $request, ProduitPharmaceutique $produit): JsonResponse
    {
        $quantite = (int)$request->request->get('quantite', 0);
        $motif = $request->request->get('motif', 'Réapprovisionnement');

        if ($quantite <= 0) {
            return $this->json(['error' => 'Quantité invalide'], 400);
        }

        $stockAvant = $produit->getStockActuel();
        $produit->incrementerStock($quantite);

        $mouvement = new MouvementStock();
        $mouvement->setProduit($produit);
        $mouvement->setUser($this->getUser());
        $mouvement->setType(MouvementStock::TYPE_ENTREE);
        $mouvement->setQuantite($quantite);
        $mouvement->setStockAvant($stockAvant);
        $mouvement->setStockApres($produit->getStockActuel());
        $mouvement->setMotif($motif);
        $this->em->persist($mouvement);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'stock_actuel' => $produit->getStockActuel(),
            'statut' => $produit->getStatutStock(),
            'message' => "Stock mis à jour: {$stockAvant} → {$produit->getStockActuel()}",
        ]);
    }

    #[Route('/{id}/signaler-rupture', name: 'app_pharmacie_rupture', methods: ['POST'])]
    #[IsGranted('ROLE_CAISSIER')]
    public function signalerRupture(Request $request, ProduitPharmaceutique $produit): JsonResponse
    {
        $motif = $request->request->get('motif', 'Rupture signalée par le caissier');
        $stockAvant = $produit->getStockActuel();

        $mouvement = new MouvementStock();
        $mouvement->setProduit($produit);
        $mouvement->setUser($this->getUser());
        $mouvement->setType(MouvementStock::TYPE_RUPTURE_SIGNALEE);
        $mouvement->setQuantite(0);
        $mouvement->setStockAvant($stockAvant);
        $mouvement->setStockApres($stockAvant);
        $mouvement->setMotif($motif);
        $this->em->persist($mouvement);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Rupture signalée. L\'administrateur sera notifié.',
        ]);
    }

    #[Route('/api/recherche', name: 'api_pharmacie_search', methods: ['GET'])]
    public function apiSearch(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        if (strlen($query) < 2) return $this->json([]);

        $produits = $this->produitRepo->searchByName($query);
        $result = [];
        foreach ($produits as $p) {
            $result[] = [
                'id' => $p->getId(),
                'nom' => $p->getNomComplet(),
                'dci' => $p->getDci(),
                'prix' => (float)$p->getPrix(),
                'stock' => $p->getStockActuel(),
                'disponible' => $p->isDisponible(),
                'statut_stock' => $p->getStatutStock(),
            ];
        }
        return $this->json($result);
    }

    #[Route('/alertes', name: 'app_pharmacie_alertes')]
    #[IsGranted('ROLE_ADMIN')]
    public function alertes(): Response
    {
        $ruptures = $this->produitRepo->findEnRupture();
        $alertes = $this->produitRepo->findEnAlerte();

        return $this->render('pharmacie/alertes.html.twig', [
            'ruptures' => $ruptures,
            'alertes' => $alertes,
        ]);
    }

    private function hydrateProduit(ProduitPharmaceutique $produit, array $data): void
    {
        if (!empty($data['nom'])) $produit->setNom($data['nom']);
        if (isset($data['dci'])) $produit->setDci($data['dci'] ?: null);
        if (isset($data['dosage'])) $produit->setDosage($data['dosage'] ?: null);
        if (isset($data['forme'])) $produit->setForme($data['forme'] ?: null);
        if (isset($data['categorie'])) $produit->setCategorie($data['categorie'] ?: null);
        if (!empty($data['prix'])) $produit->setPrix($data['prix']);
        if (isset($data['stock_actuel'])) $produit->setStockActuel((int)$data['stock_actuel']);
        if (isset($data['stock_minimum'])) $produit->setStockMinimum((int)$data['stock_minimum']);
        if (isset($data['unite'])) $produit->setUnite($data['unite'] ?: null);
        if (isset($data['description'])) $produit->setDescription($data['description'] ?: null);
        $produit->setActif(isset($data['actif']) && $data['actif'] === '1');
    }
}
