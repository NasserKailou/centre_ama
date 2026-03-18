<?php

namespace App\Controller;

use App\Repository\ConsultationRepository;
use App\Repository\FactureGlobaleRepository;
use App\Repository\UserRepository;
use App\Repository\PartenaireRepository;
use App\Repository\ProduitPharmaceutiqueRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/rapport')]
class RapportController extends AbstractController
{
    public function __construct(
        private ConsultationRepository          $consultationRepo,
        private FactureGlobaleRepository        $factureRepo,
        private UserRepository                  $userRepo,
        private PartenaireRepository            $partenaireRepo,
        private ProduitPharmaceutiqueRepository $produitRepo,
    ) {}

    #[Route('', name: 'app_rapport_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $periode    = $request->query->get('periode', 'mois');
        $medecinId  = $request->query->get('medecin_id');

        [$dateDebut, $dateFin] = $this->resoudrePeriode($periode,
            $request->query->get('date_debut'),
            $request->query->get('date_fin')
        );

        $stats = $this->buildStats($dateDebut, $dateFin, $medecinId);

        // Données graphiques
        $chartData = $this->buildChartData($dateDebut, $dateFin);

        return $this->render('rapport/index.html.twig', [
            'stats'     => $stats,
            'medecins'  => $this->userRepo->findByRole('ROLE_MEDECIN'),
            'periode'   => $periode,
            'dateDebut' => $dateDebut->format('Y-m-d'),
            'dateFin'   => $dateFin->format('Y-m-d'),
            'medecinId' => $medecinId,
            'chartData' => $chartData,
            'currency'  => $_ENV['CURRENCY'] ?? 'FCFA',
        ]);
    }

    #[Route('/export', name: 'app_rapport_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        $format    = $request->query->get('format', 'pdf');
        $periode   = $request->query->get('periode', 'mois');
        [$dateDebut, $dateFin] = $this->resoudrePeriode($periode,
            $request->query->get('date_debut'),
            $request->query->get('date_fin')
        );

        $stats = $this->buildStats($dateDebut, $dateFin, null);

        if ($format === 'pdf') {
            return $this->genererPDF($stats, $dateDebut, $dateFin);
        }

        if ($format === 'excel') {
            return $this->genererExcel($stats, $dateDebut, $dateFin);
        }

        return $this->redirectToRoute('app_rapport_index');
    }

    #[Route('/caisse-journaliere', name: 'app_rapport_caisse_journaliere', methods: ['GET'])]
    public function caisseJournaliere(Request $request): Response
    {
        $date     = new \DateTime($request->query->get('date', 'today'));
        $factures = $this->factureRepo->findByDate($date);

        $totalMedical  = 0;
        $totalPharmacie = 0;
        $totalAssurance = 0;
        $totalPatient   = 0;

        foreach ($factures as $f) {
            $totalMedical   += $f->getMontantActes() ?? 0;
            $totalPharmacie += $f->getMontantPharmacie() ?? 0;
            $totalAssurance += $f->getPartAssurance() ?? 0;
            $totalPatient   += $f->getPartPatient() ?? 0;
        }

        return $this->render('caisse/situation_journaliere.html.twig', [
            'factures'       => $factures,
            'date'           => $date,
            'totalMedical'   => $totalMedical,
            'totalPharmacie' => $totalPharmacie,
            'totalAssurance' => $totalAssurance,
            'totalPatient'   => $totalPatient,
            'grandTotal'     => $totalMedical + $totalPharmacie,
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    private function resoudrePeriode(string $periode, ?string $dDebut, ?string $dFin): array
    {
        return match($periode) {
            'aujourd_hui'=> [new \DateTime('today'), new \DateTime('today')],
            'semaine'    => [new \DateTime('monday this week'), new \DateTime('sunday this week')],
            'trimestre'  => [new \DateTime('first day of january'), new \DateTime('last day of march')],
            'annee'      => [new \DateTime('first day of january'), new \DateTime('last day of december')],
            'custom'     => [
                new \DateTime($dDebut ?: 'first day of this month'),
                new \DateTime($dFin   ?: date('Y-m-d')),
            ],
            default      => [
                new \DateTime('first day of this month'),
                new \DateTime('last day of this month')
            ],
        };
    }

    private function buildStats(\DateTime $debut, \DateTime $fin, ?string $medecinId): array
    {
        $medecins = $this->userRepo->findByRole('ROLE_MEDECIN');
        $parMedecin = [];
        $revenuTotal = 0;

        foreach ($medecins as $med) {
            if ($medecinId && $med->getId() != $medecinId) continue;

            $nb        = $this->consultationRepo->countByMedecinAndPeriode($med, $debut, $fin);
            $revenus   = $this->factureRepo->getRevenuParMedecin($med->getId(), $debut, $fin);
            $revenu    = $revenus['total'] ?? 0;
            $assurance = $revenus['assurance'] ?? 0;
            $patient   = $revenus['patient'] ?? 0;
            $revenuTotal += $revenu;

            $parMedecin[] = [
                'id'             => $med->getId(),
                'nom'            => $med->getNom(),
                'prenom'         => $med->getPrenom(),
                'specialite'     => $med->getSpecialite(),
                'nbConsultations'=> $nb,
                'revenuActes'    => $revenu,
                'partAssurance'  => $assurance,
                'partPatient'    => $patient,
                'revenuTotal'    => $revenu,
                'pourcentage'    => 0, // calculé après
            ];
        }

        // Calcul pourcentages
        foreach ($parMedecin as &$row) {
            $row['pourcentage'] = $revenuTotal > 0
                ? round(($row['revenuTotal'] / $revenuTotal) * 100, 1)
                : 0;
        }

        $globalFactures = $this->factureRepo->getGlobalStats($debut, $fin);
        $topActes       = $this->factureRepo->getTopActes($debut, $fin, 10);
        $parPartenaire  = $this->partenaireRepo->getBilanPeriode($debut, $fin);

        // Ventes pharmacie
        $ventesPharmieDetail = $this->factureRepo->getVentesPharmacieParProduit($debut, $fin);
        $ventesPharmie = array_sum(array_column($ventesPharmieDetail, 'total'));

        return [
            'parMedecin'          => $parMedecin,
            'totalPatients'       => $globalFactures['nbPatients']      ?? 0,
            'totalConsultations'  => $globalFactures['nbConsultations'] ?? 0,
            'totalFactures'       => $globalFactures['nbFactures']      ?? 0,
            'revenuTotal'         => $globalFactures['revenuTotal']     ?? 0,
            'revenuActes'         => $globalFactures['revenuActes']     ?? 0,
            'partAssurance'       => $globalFactures['partAssurance']   ?? 0,
            'partPatient'         => $globalFactures['partPatient']     ?? 0,
            'ventesPharmie'       => $ventesPharmie,
            'ventesPharmieDetail' => $ventesPharmieDetail,
            'topActes'            => $topActes,
            'parPartenaire'       => $parPartenaire,
        ];
    }

    private function buildChartData(\DateTime $debut, \DateTime $fin): array
    {
        $labels = $values = [];
        $cur = clone $debut;
        while ($cur <= $fin) {
            $labels[] = $cur->format('d/m');
            $values[] = (float)$this->factureRepo->sumRecettesByDate(clone $cur);
            $cur->modify('+1 day');
            if (count($labels) >= 60) break; // max 60 points
        }

        $actesRep = $this->factureRepo->getActesRepartition($debut, $fin);
        $medecinData = $this->consultationRepo->getConsultationsParMedecinPeriode($debut, $fin);

        return [
            'revenus'  => ['labels' => $labels, 'values' => $values],
            'actes'    => [
                'labels' => array_column($actesRep, 'designation'),
                'values' => array_column($actesRep, 'count'),
            ],
            'medecins' => [
                'labels' => array_map(fn($r) => 'Dr ' . $r['nom'], $medecinData),
                'values' => array_column($medecinData, 'count'),
            ],
        ];
    }

    private function genererPDF(array $stats, \DateTime $debut, \DateTime $fin): Response
    {
        // PDF basique via HTML
        $html = $this->renderView('rapport/pdf.html.twig', [
            'stats'     => $stats,
            'dateDebut' => $debut,
            'dateFin'   => $fin,
        ]);

        try {
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $output = $dompdf->output();

            $response = new Response($output);
            $response->headers->set('Content-Type', 'application/pdf');
            $response->headers->set('Content-Disposition',
                'attachment; filename="rapport_' . $debut->format('Y-m-d') . '_' . $fin->format('Y-m-d') . '.pdf"');
            return $response;
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erreur génération PDF: ' . $e->getMessage());
            return $this->redirectToRoute('app_rapport_index');
        }
    }

    private function genererExcel(array $stats, \DateTime $debut, \DateTime $fin): Response
    {
        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Rapport CSI');

            // En-têtes
            $sheet->setCellValue('A1', 'Rapport CSI - ' . $debut->format('d/m/Y') . ' au ' . $fin->format('d/m/Y'));
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

            $row = 3;
            $sheet->setCellValue('A'.$row, 'Médecin');
            $sheet->setCellValue('B'.$row, 'Consultations');
            $sheet->setCellValue('C'.$row, 'Revenu total');
            $sheet->setCellValue('D'.$row, 'Part assurance');
            $sheet->setCellValue('E'.$row, 'Part patient');
            $sheet->getStyle("A{$row}:E{$row}")->getFont()->setBold(true);
            $row++;

            foreach ($stats['parMedecin'] as $med) {
                $sheet->setCellValue('A'.$row, 'Dr ' . $med['nom'] . ' ' . $med['prenom']);
                $sheet->setCellValue('B'.$row, $med['nbConsultations']);
                $sheet->setCellValue('C'.$row, $med['revenuTotal']);
                $sheet->setCellValue('D'.$row, $med['partAssurance']);
                $sheet->setCellValue('E'.$row, $med['partPatient']);
                $row++;
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            ob_start();
            $writer->save('php://output');
            $content = ob_get_clean();

            $response = new Response($content);
            $response->headers->set('Content-Type',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $response->headers->set('Content-Disposition',
                'attachment; filename="rapport_' . $debut->format('Y-m-d') . '.xlsx"');
            return $response;
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erreur génération Excel: ' . $e->getMessage());
            return $this->redirectToRoute('app_rapport_index');
        }
    }
}
