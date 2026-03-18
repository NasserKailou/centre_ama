<?php

namespace App\Controller;

use App\Repository\ConsultationRepository;
use App\Repository\FactureGlobaleRepository;
use App\Repository\PatientRepository;
use App\Repository\RendezVousRepository;
use App\Repository\ProduitPharmaceutiqueRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    public function __construct(
        private ConsultationRepository          $consultationRepo,
        private FactureGlobaleRepository        $factureRepo,
        private PatientRepository               $patientRepo,
        private RendezVousRepository            $rdvRepo,
        private ProduitPharmaceutiqueRepository $produitRepo,
        private UserRepository                  $userRepo
    ) {}

    #[Route('/', name: 'app_dashboard')]
    public function index(): Response
    {
        $today     = new \DateTime('today');
        $tomorrow  = new \DateTime('tomorrow');
        $yesterday = new \DateTime('yesterday');

        // KPIs du jour
        $patientsJour       = $this->consultationRepo->countByDate($today);
        $patientsHier       = $this->consultationRepo->countByDate($yesterday);
        $consultationsJour  = $this->consultationRepo->countByDate($today);
        $rdvJour            = $this->rdvRepo->countByDate($today);
        $facturesJour       = $this->factureRepo->countByDate($today);
        $recettesJour       = $this->factureRepo->sumRecettesByDate($today);
        $ruptures           = $this->produitRepo->countRuptures();

        $variationPatients = $patientsHier > 0
            ? round((($patientsJour - $patientsHier) / $patientsHier) * 100, 1)
            : 0;

        // Alertes
        $alertes = [];
        if ($ruptures > 0) {
            $alertes[] = [
                'type'    => 'warning',
                'icon'    => 'pills',
                'titre'   => 'Pharmacie',
                'message' => "$ruptures produit(s) en rupture ou alerte de stock",
                'lien'    => '/pharmacie',
            ];
        }
        $facturesImpayees = $this->factureRepo->countImpayees();
        if ($facturesImpayees > 0) {
            $alertes[] = [
                'type'    => 'info',
                'icon'    => 'file-invoice-dollar',
                'titre'   => 'Facturation',
                'message' => "$facturesImpayees facture(s) en attente de règlement",
                'lien'    => '/caisse',
            ];
        }

        // Données graphiques — 30 derniers jours
        $chartData = $this->buildChartData();

        // Dernières consultations & prochains RDV
        $dernieresConsultations = $this->consultationRepo->findRecentByMedecin(
            $this->getUser(), 8
        );
        $prochainsRdv = $this->rdvRepo->findUpcoming(8);

        return $this->render('dashboard/index.html.twig', [
            'kpis' => [
                'patientsJour'      => $patientsJour,
                'consultationsJour' => $consultationsJour,
                'rdvJour'           => $rdvJour,
                'facturesJour'      => $facturesJour,
                'recettesJour'      => $recettesJour,
                'ruptures'          => $ruptures,
                'variationPatients' => $variationPatients,
            ],
            'kpisGlobal' => [
                'ruptures' => $ruptures,
                'rdvJour'  => $rdvJour,
            ],
            'alertes'                => $alertes,
            'chartData'              => $chartData,
            'dernieresConsultations' => $dernieresConsultations,
            'prochainsRdv'           => $prochainsRdv,
            'currency'               => $_ENV['CURRENCY'] ?? 'FCFA',
        ]);
    }

    #[Route('/api/dashboard/kpis', name: 'api_dashboard_kpis')]
    public function apiKpis(): JsonResponse
    {
        $today = new \DateTime('today');
        return new JsonResponse([
            'kpis' => [
                'patientsJour'      => $this->consultationRepo->countByDate($today),
                'consultationsJour' => $this->consultationRepo->countByDate($today),
                'rdvJour'           => $this->rdvRepo->countByDate($today),
                'facturesJour'      => $this->factureRepo->countByDate($today),
                'recettesJour'      => $this->factureRepo->sumRecettesByDate($today),
                'ruptures'          => $this->produitRepo->countRuptures(),
            ]
        ]);
    }

    private function buildChartData(): array
    {
        $labels   = [];
        $revenus  = [];
        $date     = new \DateTime('-29 days');

        for ($i = 0; $i < 30; $i++) {
            $labels[]  = $date->format('d/m');
            $revenus[] = (float)$this->factureRepo->sumRecettesByDate(clone $date);
            $date->modify('+1 day');
        }

        // Actes
        $actesData = $this->factureRepo->getActesRepartition();
        $actesLabels = $actesValues = [];
        foreach ($actesData as $row) {
            $actesLabels[] = $row['designation'] ?? 'Autre';
            $actesValues[] = (int)($row['count'] ?? 0);
        }

        // Médecins
        $medecinData    = $this->consultationRepo->getConsultationsParMedecin();
        $medecinLabels  = $medecinValues = [];
        foreach ($medecinData as $row) {
            $medecinLabels[] = 'Dr ' . ($row['nom'] ?? '?');
            $medecinValues[] = (int)($row['count'] ?? 0);
        }

        // Stock alertes
        $stockAlerts  = $this->produitRepo->findStockAlerts(10);
        $stockLabels  = $stockValues = [];
        foreach ($stockAlerts as $p) {
            $stockLabels[] = $p->getDesignation();
            $stockValues[] = $p->getStockDisponible();
        }

        return [
            'revenus'  => ['labels' => $labels,       'values' => $revenus],
            'actes'    => ['labels' => $actesLabels,   'values' => $actesValues],
            'medecins' => ['labels' => $medecinLabels, 'values' => $medecinValues],
            'stock'    => ['labels' => $stockLabels,   'values' => $stockValues],
        ];
    }
}
