<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\ConsultationRepository;
use App\Repository\RendezVousRepository;
use App\Repository\PrescriptionExamenRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/medecin')]
class MedecinController extends AbstractController
{
    public function __construct(
        private ConsultationRepository        $consultationRepo,
        private RendezVousRepository          $rdvRepo,
        private PrescriptionExamenRepository  $examenRepo,
        private EntityManagerInterface        $em,
        private UserPasswordHasherInterface   $hasher
    ) {}

    #[Route('', name: 'app_medecin_index')]
    public function index(): Response
    {
        /** @var User $user */
        $user  = $this->getUser();
        $today = new \DateTime('today');

        $rdvAujourdhui         = $this->rdvRepo->findByMedecinAndDate($user, $today);
        $examensEnAttente      = $this->examenRepo->findByMedecinAndStatut($user, 'prescrit');
        $consultationsRecentes = $this->consultationRepo->findByMedecin($user, 10);

        return $this->render('medecin/index.html.twig', [
            'rdvAujourdhui'         => $rdvAujourdhui,
            'examensEnAttente'      => $examensEnAttente,
            'consultationsRecentes' => $consultationsRecentes,
            'stats' => [
                'consultationsAujourdhui' => $this->consultationRepo->countByMedecinAndDate($user, $today),
                'rdvAujourdhui'           => count($rdvAujourdhui),
                'examensEnAttente'        => count($examensEnAttente),
                'totalPatientsMois'       => $this->consultationRepo->countPatientsMoisByMedecin($user),
            ],
        ]);
    }

    // ── Profil médecin ────────────────────────────────────────────
    #[Route('/profil', name: 'app_medecin_profile')]
    public function profile(): Response
    {
        /** @var User $user */
        $user  = $this->getUser();
        $today = new \DateTime('today');

        return $this->render('medecin/profile.html.twig', [
            'stats' => [
                'today' => $this->consultationRepo->countByMedecinAndDate($user, $today),
                'mois'  => $this->consultationRepo->countPatientsMoisByMedecin($user),
                'total' => $this->consultationRepo->countByMedecin($user),
            ],
        ]);
    }

    // ── Modifier profil ───────────────────────────────────────────
    #[Route('/profil/modifier', name: 'app_medecin_update_profile', methods: ['POST'])]
    public function updateProfile(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('update_profile', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_medecin_profile');
        }

        /** @var User $user */
        $user = $this->getUser();
        $data = $request->request->all();

        if (!empty($data['nom']))       $user->setNom($data['nom']);
        if (!empty($data['prenom']))    $user->setPrenom($data['prenom']);
        if (isset($data['telephone']))  $user->setTelephone($data['telephone'] ?: null);
        if (isset($data['specialite'])) $user->setSpecialite($data['specialite'] ?: null);

        $this->em->flush();
        $this->addFlash('success', 'Profil mis à jour avec succès.');
        return $this->redirectToRoute('app_medecin_profile');
    }

    // ── Changer mot de passe ──────────────────────────────────────
    #[Route('/profil/mot-de-passe', name: 'app_medecin_change_password', methods: ['POST'])]
    public function changePassword(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('change_password', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_medecin_profile');
        }

        /** @var User $user */
        $user           = $this->getUser();
        $currentPwd     = $request->request->get('current_password', '');
        $newPwd         = $request->request->get('new_password', '');
        $confirmPwd     = $request->request->get('confirm_password', '');

        if (!$this->hasher->isPasswordValid($user, $currentPwd)) {
            $this->addFlash('danger', 'Mot de passe actuel incorrect.');
            return $this->redirectToRoute('app_medecin_profile');
        }

        if ($newPwd !== $confirmPwd || strlen($newPwd) < 8) {
            $this->addFlash('danger', 'Les mots de passe ne correspondent pas ou sont trop courts (min. 8 caractères).');
            return $this->redirectToRoute('app_medecin_profile');
        }

        $user->setPassword($this->hasher->hashPassword($user, $newPwd));
        $this->em->flush();

        $this->addFlash('success', 'Mot de passe modifié avec succès.');
        return $this->redirectToRoute('app_medecin_profile');
    }
}
