<?php

namespace App\Controller;

use App\Entity\RendezVous;
use App\Repository\RendezVousRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\PatientRepository;

#[Route('/rdv')]
class RendezVousController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private RendezVousRepository   $rdvRepo,
        private PatientRepository      $patientRepo,
        private UserRepository         $userRepo
    ) {}

    #[Route('', name: 'app_rdv_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $vue       = $request->query->get('vue', 'liste');
        $periode   = $request->query->get('semaine', null);
        $medecinId = $request->query->get('medecin_id');
        $statut    = $request->query->get('statut');

        // Calcul plage de dates
        if ($vue === 'calendrier') {
            $dateDebut = $periode
                ? new \DateTime($periode)
                : new \DateTime('monday this week');
            $dateFin   = (clone $dateDebut)->modify('+6 days');
        } else {
            $dateDebutStr = $request->query->get('date_debut',
                (new \DateTime('first day of this month'))->format('Y-m-d'));
            $dateFinStr   = $request->query->get('date_fin',
                (new \DateTime())->format('Y-m-d'));
            $dateDebut    = new \DateTime($dateDebutStr);
            $dateFin      = new \DateTime($dateFinStr);
        }

        $rdvs = $this->rdvRepo->findByFilters(
            $dateDebut, $dateFin, $medecinId ? (int)$medecinId : null, $statut
        );

        // Construire structure calendrier
        $rdvParJourHeure = [];
        $joursCalendrier = [];
        $heures = ['08:00','08:30','09:00','09:30','10:00','10:30','11:00','11:30',
                   '12:00','12:30','14:00','14:30','15:00','15:30','16:00','16:30','17:00'];
        $today  = new \DateTime();
        $today->setTime(0,0,0);

        if ($vue === 'calendrier') {
            $jours = ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'];
            $cur   = clone $dateDebut;
            for ($i = 0; $i < 7; $i++) {
                $ds = $cur->format('Y-m-d');
                $joursCalendrier[] = [
                    'dateStr' => $ds,
                    'label'   => $jours[$i],
                    'date'    => clone $cur,
                    'isToday' => $cur->format('Y-m-d') === $today->format('Y-m-d'),
                ];
                $cur->modify('+1 day');
            }
            foreach ($rdvs as $rdv) {
                $ds = $rdv->getDateHeure()->format('Y-m-d');
                $h  = $rdv->getDateHeure()->format('H:i');
                // Trouver la tranche la plus proche
                $tranche = $this->trouverTranche($h, $heures);
                $rdvParJourHeure[$ds][$tranche][] = $rdv;
            }
        }

        return $this->render('rdv/index.html.twig', [
            'rdvs'            => $rdvs,
            'medecins'        => $this->userRepo->findByRole('ROLE_MEDECIN'),
            'vue'             => $vue,
            'dateDebut'       => $dateDebut->format('Y-m-d'),
            'dateFin'         => $dateFin->format('Y-m-d'),
            'medecinId'       => $medecinId,
            'statut'          => $statut,
            'joursCalendrier' => $joursCalendrier,
            'heures'          => $heures,
            'rdvParJourHeure' => $rdvParJourHeure,
            'semainePrev'     => $vue === 'calendrier' ? (clone $dateDebut)->modify('-7 days')->format('Y-m-d') : null,
            'semaineNext'     => $vue === 'calendrier' ? (clone $dateDebut)->modify('+7 days')->format('Y-m-d') : null,
        ]);
    }

    #[Route('/new', name: 'app_rdv_new', methods: ['GET','POST'])]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $patientId  = $request->request->get('patient_id');
            $medecinId  = $request->request->get('medecin_id');
            $dateHeure  = $request->request->get('date_heure');
            $motif      = $request->request->get('motif');
            $duree      = $request->request->getInt('duree', 30);
            $notes      = $request->request->get('notes');

            $patient = $this->patientRepo->find((int)$patientId);
            $medecin = $this->userRepo->find((int)$medecinId);

            if (!$patient || !$medecin) {
                $this->addFlash('danger', 'Patient ou médecin introuvable.');
                return $this->redirectToRoute('app_rdv_index');
            }

            $rdv = new RendezVous();
            $rdv->setPatient($patient)
                ->setMedecin($medecin)
                ->setDateHeure(new \DateTime($dateHeure))
                ->setMotif($motif)
                ->setDuree($duree)
                ->setNotes($notes)
                ->setStatut('planifie')
                ->setCreatedAt(new \DateTime());

            $this->em->persist($rdv);
            $this->em->flush();

            $this->addFlash('success', 'Rendez-vous créé avec succès.');
            return $this->redirectToRoute('app_rdv_show', ['id' => $rdv->getId()]);
        }

        return $this->render('rdv/index.html.twig', [
            'medecins'  => $this->userRepo->findByRole('ROLE_MEDECIN'),
            'vue'       => 'liste',
            'rdvs'      => [],
            'dateDebut' => (new \DateTime('first day of this month'))->format('Y-m-d'),
            'dateFin'   => (new \DateTime())->format('Y-m-d'),
        ]);
    }

    #[Route('/{id}', name: 'app_rdv_show', methods: ['GET'])]
    public function show(RendezVous $rdv): Response
    {
        return $this->render('rdv/show.html.twig', ['rdv' => $rdv]);
    }

    #[Route('/{id}/edit', name: 'app_rdv_edit', methods: ['GET','POST'])]
    public function edit(RendezVous $rdv, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $medecinId = $request->request->get('medecin_id');
            $dateHeure = $request->request->get('date_heure');
            $medecin   = $this->userRepo->find((int)$medecinId);

            if ($medecin) $rdv->setMedecin($medecin);
            $rdv->setDateHeure(new \DateTime($dateHeure))
                ->setMotif($request->request->get('motif'))
                ->setDuree($request->request->getInt('duree', 30))
                ->setNotes($request->request->get('notes'));

            $this->em->flush();
            $this->addFlash('success', 'Rendez-vous modifié.');
            return $this->redirectToRoute('app_rdv_show', ['id' => $rdv->getId()]);
        }

        return $this->render('rdv/show.html.twig', [
            'rdv'      => $rdv,
            'medecins' => $this->userRepo->findByRole('ROLE_MEDECIN'),
        ]);
    }

    #[Route('/{id}/confirm', name: 'app_rdv_confirm', methods: ['POST'])]
    public function confirm(RendezVous $rdv): Response
    {
        $rdv->setStatut('confirme');
        $this->em->flush();
        $this->addFlash('success', 'Rendez-vous confirmé.');
        return $this->redirectToRoute('app_rdv_show', ['id' => $rdv->getId()]);
    }

    #[Route('/{id}/cancel', name: 'app_rdv_cancel', methods: ['POST'])]
    public function cancel(RendezVous $rdv): Response
    {
        $rdv->setStatut('annule');
        $this->em->flush();
        $this->addFlash('info', 'Rendez-vous annulé.');
        return $this->redirectToRoute('app_rdv_show', ['id' => $rdv->getId()]);
    }

    // Helper : trouver la tranche horaire la plus proche
    private function trouverTranche(string $heure, array $tranches): string
    {
        $hMin = (int)str_replace(':', '', $heure);
        $best = $tranches[0];
        $diff = PHP_INT_MAX;
        foreach ($tranches as $t) {
            $tMin = (int)str_replace(':', '', $t);
            if (abs($tMin - $hMin) < $diff) {
                $diff = abs($tMin - $hMin);
                $best = $t;
            }
        }
        return $best;
    }
}
