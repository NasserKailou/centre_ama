<?php

namespace App\Controller;

use App\Entity\Patient;
use App\Repository\ConsultationRepository;
use App\Repository\FactureGlobaleRepository;
use App\Repository\PatientRepository;
use App\Repository\RendezVousRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/patients')]
#[IsGranted('ROLE_USER')]
class PatientController extends AbstractController
{
    public function __construct(
        private PatientRepository $patientRepo,
        private EntityManagerInterface $em,
        private PaginatorInterface $paginator,
        private ConsultationRepository $consultationRepo,
        private FactureGlobaleRepository $factureRepo,
        private RendezVousRepository $rdvRepo
    ) {}

    #[Route('/', name: 'app_patient_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search = $request->query->get('search', '');
        $qb = $this->patientRepo->getSearchQueryBuilder($search);

        $pagination = $this->paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            20
        );

        return $this->render('patient/index.html.twig', [
            'pagination' => $pagination,
            'search' => $search,
        ]);
    }

    #[Route('/nouveau', name: 'app_patient_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ASSISTANT')]
    public function new(Request $request, ValidatorInterface $validator): Response
    {
        $patient = new Patient();

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $this->hydratePatient($patient, $data);

            $errors = $validator->validate($patient);
            if (count($errors) === 0) {
                // Générer numéro dossier unique
                $patient->setNumeroDossier($this->generateNumeroDossier());
                $this->em->persist($patient);
                $this->em->flush();

                $this->addFlash('success', "Patient {$patient->getNomComplet()} créé avec succès. N° Dossier: {$patient->getNumeroDossier()}");
                return $this->redirectToRoute('app_patient_show', ['id' => $patient->getId()]);
            }
            foreach ($errors as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->render('patient/new.html.twig', ['patient' => $patient]);
    }

    #[Route('/{id}', name: 'app_patient_show', methods: ['GET'])]
    public function show(Patient $patient): Response
    {
        $dossierMedical = [
            'consultations' => $this->consultationRepo->findBy(['patient' => $patient], ['dateHeure' => 'DESC'], 20),
            'factures' => $this->factureRepo->findBy(['patient' => $patient], ['createdAt' => 'DESC'], 10),
            'rdv' => $this->rdvRepo->findBy(['patient' => $patient], ['dateHeure' => 'DESC'], 10),
            'derniere_consultation' => $this->consultationRepo->findOneBy(['patient' => $patient], ['dateHeure' => 'DESC']),
        ];

        return $this->render('patient/show.html.twig', [
            'patient' => $patient,
            'dossier_medical' => $dossierMedical,
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_patient_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Patient $patient, ValidatorInterface $validator): Response
    {
        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $this->hydratePatient($patient, $data);

            $errors = $validator->validate($patient);
            if (count($errors) === 0) {
                $this->em->flush();
                $this->addFlash('success', 'Dossier patient mis à jour avec succès.');
                return $this->redirectToRoute('app_patient_show', ['id' => $patient->getId()]);
            }
            foreach ($errors as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->render('patient/edit.html.twig', ['patient' => $patient]);
    }

    #[Route('/api/recherche', name: 'api_patient_search', methods: ['GET'])]
    public function apiSearch(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        if (strlen($query) < 3) {
            return $this->json([]);
        }

        $patients = $this->patientRepo->searchByPhone($query);
        $results = [];
        foreach ($patients as $patient) {
            $results[] = [
                'id' => $patient->getId(),
                'nom_complet' => $patient->getNomComplet(),
                'telephone' => $patient->getTelephone(),
                'numero_dossier' => $patient->getNumeroDossier(),
                'age' => $patient->getAge(),
                'sexe' => $patient->getSexe(),
            ];
        }

        return $this->json($results);
    }

    #[Route('/api/{id}/info', name: 'api_patient_info', methods: ['GET'])]
    public function apiInfo(Patient $patient): JsonResponse
    {
        return $this->json([
            'id' => $patient->getId(),
            'nom_complet' => $patient->getNomComplet(),
            'nom' => $patient->getNom(),
            'prenom' => $patient->getPrenom(),
            'telephone' => $patient->getTelephone(),
            'numero_dossier' => $patient->getNumeroDossier(),
            'age' => $patient->getAge(),
            'sexe' => $patient->getSexeLibelle(),
            'groupe_sanguin' => $patient->getGroupeSanguin(),
            'allergies' => $patient->getAllergies(),
        ]);
    }

    private function hydratePatient(Patient $patient, array $data): void
    {
        if (!empty($data['nom'])) $patient->setNom($data['nom']);
        if (!empty($data['prenom'])) $patient->setPrenom($data['prenom']);
        if (!empty($data['telephone'])) $patient->setTelephone($data['telephone']);
        if (isset($data['telephone2'])) $patient->setTelephone2($data['telephone2'] ?: null);
        if (!empty($data['date_naissance'])) {
            $patient->setDateNaissance(new \DateTime($data['date_naissance']));
        }
        if (!empty($data['sexe'])) $patient->setSexe($data['sexe']);
        if (isset($data['groupe_sanguin'])) $patient->setGroupeSanguin($data['groupe_sanguin'] ?: null);
        if (isset($data['allergies'])) $patient->setAllergies($data['allergies'] ?: null);
        if (isset($data['antecedents_medicaux'])) $patient->setAntecedentsMedicaux($data['antecedents_medicaux'] ?: null);
        if (isset($data['antecedents_chirurgicaux'])) $patient->setAntecedentsChirurgicaux($data['antecedents_chirurgicaux'] ?: null);
        if (isset($data['antecedents_familiaux'])) $patient->setAntecedentsFamiliaux($data['antecedents_familiaux'] ?: null);
        if (isset($data['adresse'])) $patient->setAdresse($data['adresse'] ?: null);
        if (isset($data['profession'])) $patient->setProfession($data['profession'] ?: null);
        if (isset($data['contact_urgence'])) $patient->setContactUrgence($data['contact_urgence'] ?: null);
        if (isset($data['telephone_urgence'])) $patient->setTelephoneUrgence($data['telephone_urgence'] ?: null);
    }

    private function generateNumeroDossier(): string
    {
        $year = date('Y');
        $lastPatient = $this->patientRepo->findOneBy([], ['id' => 'DESC']);
        $nextId = $lastPatient ? $lastPatient->getId() + 1 : 1;
        return sprintf('CSI-%s-%05d', $year, $nextId);
    }
}
