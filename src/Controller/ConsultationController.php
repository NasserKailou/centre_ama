<?php

namespace App\Controller;

use App\Entity\Consultation;
use App\Entity\PrescriptionExamen;
use App\Repository\ActeMedicalRepository;
use App\Repository\ConsultationRepository;
use App\Repository\PatientRepository;
use App\Repository\PrescriptionExamenRepository;
use App\Repository\RendezVousRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/consultations')]
#[IsGranted('ROLE_USER')]
class ConsultationController extends AbstractController
{
    public function __construct(
        private ConsultationRepository $consultationRepo,
        private PatientRepository $patientRepo,
        private ActeMedicalRepository $acteRepo,
        private PrescriptionExamenRepository $prescriptionRepo,
        private RendezVousRepository $rdvRepo,
        private EntityManagerInterface $em,
        private PaginatorInterface $paginator
    ) {}

    #[Route('/', name: 'app_consultation_index')]
    public function index(Request $request): Response
    {
        $search = $request->query->get('search', '');
        $date = $request->query->get('date', '');
        $statut = $request->query->get('statut', '');

        $qb = $this->consultationRepo->getFilteredQueryBuilder($search, $date, $statut, $this->getUser());
        $pagination = $this->paginator->paginate($qb, $request->query->getInt('page', 1), 20);

        // Données pour le modal de création rapide
        $patients = $this->patientRepo->findBy([], ['nom' => 'ASC'], 200);

        return $this->render('consultation/index.html.twig', [
            'pagination'    => $pagination,
            'search'        => $search,
            'date_filter'   => $date,
            'statut_filter' => $statut,
            'patients'      => $patients,
        ]);
    }

    #[Route('/nouvelle', name: 'app_consultation_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_MEDECIN')]
    public function new(Request $request): Response
    {
        $consultation = new Consultation();
        // 'type' n'est plus un champ → utiliser 'categorie', 'libelle' → 'designation'
        $actesExamens = $this->acteRepo->findByCategories(['examen', 'soin']);

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $patient = $this->patientRepo->find($data['patient_id'] ?? 0);

            if (!$patient) {
                $this->addFlash('error', 'Patient non trouvé.');
            } else {
                $consultation->setPatient($patient);
                $consultation->setMedecin($this->getUser());
                $consultation->setDateHeure(new \DateTime());
                $consultation->setMotif($data['motif'] ?? 'Consultation');
                $consultation->setObservations($data['observations'] ?? null);
                $consultation->setDiagnostic($data['diagnostic'] ?? null);
                $consultation->setTraitement($data['traitement'] ?? null);
                // setOrdonnance n'existe pas → on met l'ordonnance dans traitement si besoin
                if (!empty($data['ordonnance'])) {
                    $existing = $consultation->getTraitement() ?? '';
                    $consultation->setTraitement($existing . "\nOrdonnance : " . $data['ordonnance']);
                }

                // Constantes vitales
                $consultation->setPoids(!empty($data['poids']) ? (float)$data['poids'] : null);
                $consultation->setTaille(!empty($data['taille']) ? (float)$data['taille'] : null);
                // setTensionArterielle n'existe pas → utiliser setTension
                $consultation->setTension($data['tension_arterielle'] ?? $data['tension'] ?? null);
                $consultation->setTemperature(!empty($data['temperature']) ? (float)$data['temperature'] : null);
                $consultation->setFrequenceCardiaque(!empty($data['frequence_cardiaque']) ? (int)$data['frequence_cardiaque'] : null);
                // setSaturationOxygene n'existe pas dans l'entité → ignorer ou stocker dans observations
                if (!empty($data['saturation_oxygene'])) {
                    $obs = $consultation->getObservations() ?? '';
                    $consultation->setObservations($obs . (!empty($obs) ? "\n" : '') . 'SpO2 : ' . $data['saturation_oxygene'] . '%');
                }

                // Lier au RDV si fourni
                if (!empty($data['rdv_id'])) {
                    $rdv = $this->rdvRepo->find($data['rdv_id']);
                    if ($rdv) {
                        $consultation->setRendezVous($rdv);
                        $rdv->setStatut('en_cours');
                    }
                }

                $this->em->persist($consultation);

                // Prescriptions examens
                if (!empty($data['examens']) && is_array($data['examens'])) {
                    foreach ($data['examens'] as $examenId) {
                        $acte = $this->acteRepo->find($examenId);
                        if ($acte) {
                            $prescription = new PrescriptionExamen();
                            $prescription->setConsultation($consultation);
                            $prescription->setActeMedical($acte);
                            $prescription->setNotes($data['notes_examens'][$examenId] ?? null);
                            $this->em->persist($prescription);
                        }
                    }
                }

                $this->em->flush();
                $this->addFlash('success', 'Consultation enregistrée avec succès.');
                return $this->redirectToRoute('app_consultation_show', ['id' => $consultation->getId()]);
            }
        }

        $patientId = $request->query->get('patient_id');
        $patient = $patientId ? $this->patientRepo->find($patientId) : null;
        $rdvId = $request->query->get('rdv_id');
        $rdv = $rdvId ? $this->rdvRepo->find($rdvId) : null;

        return $this->render('consultation/new.html.twig', [
            'consultation' => $consultation,
            'patient' => $patient,
            'rdv' => $rdv,
            'actes_examens' => $actesExamens,
        ]);
    }

    #[Route('/{id}', name: 'app_consultation_show', methods: ['GET'])]
    public function show(Consultation $consultation): Response
    {
        return $this->render('consultation/show.html.twig', [
            'consultation' => $consultation,
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_consultation_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_MEDECIN')]
    public function edit(Request $request, Consultation $consultation): Response
    {
        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $consultation->setMotif($data['motif'] ?? $consultation->getMotif());
            $consultation->setObservations($data['observations'] ?? null);
            $consultation->setDiagnostic($data['diagnostic'] ?? null);
            $consultation->setTraitement($data['traitement'] ?? null);
            $consultation->setPoids(!empty($data['poids']) ? (float)$data['poids'] : null);
            $consultation->setTaille(!empty($data['taille']) ? (float)$data['taille'] : null);
            $consultation->setTension($data['tension_arterielle'] ?? $data['tension'] ?? null);
            $consultation->setTemperature(!empty($data['temperature']) ? (float)$data['temperature'] : null);
            $consultation->setFrequenceCardiaque(!empty($data['frequence_cardiaque']) ? (int)$data['frequence_cardiaque'] : null);

            if (!empty($data['statut'])) $consultation->setStatut($data['statut']);

            $this->em->flush();
            $this->addFlash('success', 'Consultation mise à jour.');
            return $this->redirectToRoute('app_consultation_show', ['id' => $consultation->getId()]);
        }

        $actesExamens = $this->acteRepo->findByCategories(['examen', 'soin']);
        return $this->render('consultation/edit.html.twig', [
            'consultation' => $consultation,
            'actes_examens' => $actesExamens,
        ]);
    }

    #[Route('/prescription/{id}/resultat', name: 'app_prescription_resultat', methods: ['POST'])]
    public function saisirResultat(Request $request, PrescriptionExamen $prescription): JsonResponse
    {
        $resultat = $request->request->get('resultat');
        $prescription->setResultat($resultat);
        $prescription->setStatut(PrescriptionExamen::STATUT_RESULTAT_SAISI);
        $prescription->setDateResultat(new \DateTimeImmutable());
        $this->em->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/file-attente', name: 'app_consultation_file_attente')]
    #[IsGranted('ROLE_MEDECIN')]
    public function fileAttente(): Response
    {
        $rdvsAttente = $this->rdvRepo->findBy(
            ['medecin' => $this->getUser(), 'statut' => ['prevu', 'arrive']],
            ['dateHeure' => 'ASC']
        );

        return $this->render('consultation/file_attente.html.twig', [
            'rdvs' => $rdvsAttente,
        ]);
    }
}
