<?php

namespace App\Controller;

use App\Entity\ActeMedical;
use App\Entity\User;
use App\Repository\ActeMedicalRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepo,
        private ActeMedicalRepository $acteRepo,
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
        private PaginatorInterface $paginator
    ) {}

    // =====================================================================
    // PAGE PRINCIPALE ADMIN
    // =====================================================================

    #[Route('', name: 'app_admin_index')]
    public function index(): Response
    {
        return $this->render('admin/index.html.twig', [
            'users' => $this->userRepo->findBy([], ['nom' => 'ASC']),
            'actes' => $this->acteRepo->findBy(['actif' => true]),
        ]);
    }

    // =====================================================================
    // GESTION UTILISATEURS
    // =====================================================================

    /**
     * Créer un utilisateur (POST depuis le modal de la page admin/index)
     */
    #[Route('/user/create', name: 'app_admin_create_user', methods: ['POST'])]
    public function createUser(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('create_user', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_index');
        }

        $data    = $request->request->all();
        $password = $data['password'] ?? '';
        $confirm  = $data['password_confirm'] ?? '';

        if ($password !== $confirm || strlen($password) < 8) {
            $this->addFlash('danger', 'Les mots de passe ne correspondent pas ou sont trop courts (min. 8 caractères).');
            return $this->redirectToRoute('app_admin_index');
        }

        $user = new User();
        $this->hydrateUser($user, $data);
        $user->setActif(true);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();

        $this->addFlash('success', "Utilisateur {$user->getPrenom()} {$user->getNom()} créé avec succès.");
        return $this->redirectToRoute('app_admin_index');
    }

    /**
     * Modifier un utilisateur (POST depuis le modal de la page admin/index)
     */
    #[Route('/user/{id}/edit', name: 'app_admin_edit_user', methods: ['POST'])]
    public function editUserModal(Request $request, User $user): Response
    {
        if (!$this->isCsrfTokenValid('edit_user_' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_index');
        }

        $data = $request->request->all();
        $this->hydrateUser($user, $data);

        $password = $data['password'] ?? '';
        if (!empty($password) && strlen($password) >= 8) {
            $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        }

        $this->em->flush();
        $this->addFlash('success', "Utilisateur {$user->getPrenom()} {$user->getNom()} mis à jour.");
        return $this->redirectToRoute('app_admin_index');
    }

    /**
     * Activer / Désactiver un utilisateur
     */
    #[Route('/user/{id}/toggle', name: 'app_admin_toggle_user', methods: ['POST'])]
    public function toggleUser(Request $request, User $user): Response
    {
        if (!$this->isCsrfTokenValid('toggle_user_' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_index');
        }

        if ($user === $this->getUser()) {
            $this->addFlash('danger', 'Impossible de modifier votre propre statut.');
            return $this->redirectToRoute('app_admin_index');
        }

        $user->setActif(!$user->isActif());
        $this->em->flush();

        $statut = $user->isActif() ? 'activé' : 'désactivé';
        $this->addFlash('success', "Compte de {$user->getPrenom()} {$user->getNom()} {$statut}.");
        return $this->redirectToRoute('app_admin_index');
    }

    /**
     * Réinitialiser le mot de passe d'un utilisateur
     */
    #[Route('/user/{id}/reset-password', name: 'app_admin_reset_password', methods: ['POST'])]
    public function resetPassword(Request $request, User $user): Response
    {
        if (!$this->isCsrfTokenValid('reset_pwd_' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_index');
        }

        $tempPwd = 'CSI@' . random_int(10000, 99999);
        $user->setPassword($this->passwordHasher->hashPassword($user, $tempPwd));
        $this->em->flush();

        $this->addFlash('success', "Mot de passe de {$user->getPrenom()} {$user->getNom()} réinitialisé. Nouveau MDP : <strong>{$tempPwd}</strong>");
        return $this->redirectToRoute('app_admin_index');
    }

    // =====================================================================
    // GESTION ACTES MÉDICAUX
    // =====================================================================

    #[Route('/actes-medicaux', name: 'app_admin_actes')]
    public function actesMedicaux(Request $request): Response
    {
        $search = $request->query->get('search', '');
        $categorie = $request->query->get('categorie', '');

        $qb = $this->acteRepo->createQueryBuilder('a')
            ->orderBy('a.categorie', 'ASC')
            ->addOrderBy('a.designation', 'ASC');

        if ($search) {
            $qb->andWhere('a.designation LIKE :search')
               ->setParameter('search', "%{$search}%");
        }
        if ($categorie) {
            $qb->andWhere('a.categorie = :categorie')
               ->setParameter('categorie', $categorie);
        }

        $pagination = $this->paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            25
        );

        return $this->render('admin/actes.html.twig', [
            'pagination'   => $pagination,
            'search'       => $search,
            'cat_filter'   => $categorie,
        ]);
    }

    #[Route('/actes-medicaux/nouveau', name: 'app_admin_acte_new', methods: ['GET', 'POST'])]
    public function newActe(Request $request): Response
    {
        $acte = new ActeMedical();

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $this->hydrateActe($acte, $data);
            $this->em->persist($acte);
            $this->em->flush();
            $this->addFlash('success', "Acte '{$acte->getDesignation()}' créé.");
            return $this->redirectToRoute('app_admin_actes');
        }

        return $this->render('admin/acte_new.html.twig', ['acte' => $acte]);
    }

    #[Route('/actes-medicaux/{id}/modifier', name: 'app_admin_acte_edit', methods: ['GET', 'POST'])]
    public function editActe(Request $request, ActeMedical $acte): Response
    {
        if ($request->isMethod('POST')) {
            $this->hydrateActe($acte, $request->request->all());
            $this->em->flush();
            $this->addFlash('success', 'Acte mis à jour.');
            return $this->redirectToRoute('app_admin_actes');
        }

        return $this->render('admin/acte_edit.html.twig', ['acte' => $acte]);
    }

    // =====================================================================
    // MÉTHODES PRIVÉES
    // =====================================================================

    private function hydrateUser(User $user, array $data): void
    {
        if (!empty($data['nom']))       $user->setNom($data['nom']);
        if (!empty($data['prenom']))    $user->setPrenom($data['prenom']);
        if (isset($data['email']))      $user->setEmail($data['email'] ?: null);
        if (isset($data['telephone']))  $user->setTelephone($data['telephone'] ?: null);
        if (isset($data['specialite'])) $user->setSpecialite($data['specialite'] ?: null);
        if (!empty($data['role']))      $user->setRoles([$data['role']]);
    }

    private function hydrateActe(ActeMedical $acte, array $data): void
    {
        if (!empty($data['designation']))       $acte->setDesignation($data['designation']);
        if (isset($data['code']))               $acte->setCode($data['code'] ?: null);
        if (isset($data['categorie']))          $acte->setCategorie($data['categorie'] ?: null);
        if (isset($data['prix_normal']))        $acte->setPrixNormal((float)$data['prix_normal']);
        if (isset($data['prix_pris_en_charge'])) $acte->setPrixPrisEnCharge((float)$data['prix_pris_en_charge']);
        $acte->setActif(isset($data['actif']) && $data['actif'] === '1');
    }
}
