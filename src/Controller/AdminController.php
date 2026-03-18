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

    // ==================== GESTION UTILISATEURS ====================

    // ── Page principale admin ──────────────────────────────────────
    #[Route('', name: 'app_admin_index')]
    public function index(): Response
    {
        return $this->render('admin/index.html.twig', [
            'users' => $this->userRepo->findBy([], ['nom' => 'ASC']),
            'actes' => $this->acteRepo->findBy(['actif' => true]),
        ]);
    }

    // ── Créer utilisateur (POST depuis modal) ─────────────────────
    #[Route('/user/create', name: 'app_admin_create_user', methods: ['POST'])]
    public function createUser(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('create_user', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_index');
        }

        $data = $request->request->all();
        $password  = $data['password'] ?? '';
        $confirm   = $data['password_confirm'] ?? '';

        if ($password !== $confirm || strlen($password) < 8) {
            $this->addFlash('danger', 'Les mots de passe ne correspondent pas ou sont trop courts.');
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

    // ── Modifier utilisateur (POST depuis modal) ──────────────────
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

    // ── Toggle actif/inactif ──────────────────────────────────────
    #[Route('/user/{id}/toggle', name: 'app_admin_toggle_user', methods: ['POST'])]
    public function toggleUser(User $user): Response
    {
        if ($user === $this->getUser()) {
            $this->addFlash('danger', 'Impossible de modifier votre propre statut.');
            return $this->redirectToRoute('app_admin_index');
        }
        $user->setActif(!$user->isActif());
        $this->em->flush();
        $this->addFlash('success', 'Statut utilisateur modifié.');
        return $this->redirectToRoute('app_admin_index');
    }

    // ── Réinitialiser le mot de passe ─────────────────────────────
    #[Route('/user/{id}/reset-password', name: 'app_admin_reset_password', methods: ['POST'])]
    public function resetPassword(User $user): Response
    {
        if (!$this->isCsrfTokenValid('reset_pwd_' . $user->getId(), $_POST['_token'] ?? '')) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_index');
        }

        // Générer un mot de passe temporaire
        $tempPwd = 'CSI@' . random_int(10000, 99999);
        $user->setPassword($this->passwordHasher->hashPassword($user, $tempPwd));
        $this->em->flush();

        $this->addFlash('success', "Mot de passe de {$user->getPrenom()} {$user->getNom()} réinitialisé : <strong>{$tempPwd}</strong>");
        return $this->redirectToRoute('app_admin_index');
    }

    // ── Liste utilisateurs (pagination) ───────────────────────────
    #[Route('/utilisateurs', name: 'app_admin_users')]
    public function users(Request $request): Response
    {
        $pagination = $this->paginator->paginate(
            $this->userRepo->createQueryBuilder('u')->orderBy('u.nom', 'ASC'),
            $request->query->getInt('page', 1),
            20
        );
        return $this->render('admin/users.html.twig', ['pagination' => $pagination]);
    }

    #[Route('/utilisateurs/nouveau', name: 'app_admin_user_new', methods: ['GET', 'POST'])]
    public function newUser(Request $request): Response
    {
        $user = new User();

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $this->hydrateUser($user, $data);

            $plainPassword = $data['password'] ?? '';
            if (strlen($plainPassword) >= 6) {
                $hashed = $this->passwordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashed);
                $this->em->persist($user);
                $this->em->flush();
                $this->addFlash('success', "Utilisateur {$user->getNomComplet()} créé avec succès.");
                return $this->redirectToRoute('app_admin_users');
            } else {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 6 caractères.');
            }
        }

        return $this->render('admin/user_new.html.twig', ['user' => $user]);
    }

    #[Route('/utilisateurs/{id}/modifier', name: 'app_admin_user_edit', methods: ['GET', 'POST'])]
    public function editUser(Request $request, User $user): Response
    {
        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $this->hydrateUser($user, $data);

            if (!empty($data['password']) && strlen($data['password']) >= 6) {
                $hashed = $this->passwordHasher->hashPassword($user, $data['password']);
                $user->setPassword($hashed);
            }

            $this->em->flush();
            $this->addFlash('success', 'Utilisateur mis à jour.');
            return $this->redirectToRoute('app_admin_users');
        }

        return $this->render('admin/user_edit.html.twig', ['user' => $user]);
    }

    #[Route('/utilisateurs/{id}/toggle', name: 'app_admin_user_toggle', methods: ['POST'])]
    public function toggleUser(User $user): Response
    {
        if ($user === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas désactiver votre propre compte.');
            return $this->redirectToRoute('app_admin_users');
        }
        $user->setActif(!$user->isActif());
        $this->em->flush();
        $this->addFlash('success', 'Statut utilisateur modifié.');
        return $this->redirectToRoute('app_admin_users');
    }

    // ==================== GESTION ACTES MEDICAUX ====================

    #[Route('/actes-medicaux', name: 'app_admin_actes')]
    public function actesMedicaux(Request $request): Response
    {
        $search = $request->query->get('search', '');
        $type = $request->query->get('type', '');

        $qb = $this->acteRepo->createQueryBuilder('a')
            ->orderBy('a.categorie', 'ASC')
            ->addOrderBy('a.libelle', 'ASC');

        if ($search) {
            $qb->andWhere('a.libelle LIKE :search')->setParameter('search', "%$search%");
        }
        if ($type) {
            $qb->andWhere('a.type = :type')->setParameter('type', $type);
        }

        $pagination = $this->paginator->paginate($qb, $request->query->getInt('page', 1), 25);

        return $this->render('admin/actes.html.twig', [
            'pagination' => $pagination,
            'search' => $search,
            'type_filter' => $type,
            'types' => ActeMedical::TYPES,
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
            $this->addFlash('success', "Acte '{$acte->getLibelle()}' créé.");
            return $this->redirectToRoute('app_admin_actes');
        }

        return $this->render('admin/acte_new.html.twig', [
            'acte' => $acte,
            'types' => ActeMedical::TYPES,
            'categories' => ActeMedical::CATEGORIES,
        ]);
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

        return $this->render('admin/acte_edit.html.twig', [
            'acte' => $acte,
            'types' => ActeMedical::TYPES,
            'categories' => ActeMedical::CATEGORIES,
        ]);
    }

    private function hydrateUser(User $user, array $data): void
    {
        if (!empty($data['nom'])) $user->setNom($data['nom']);
        if (!empty($data['prenom'])) $user->setPrenom($data['prenom']);
        if (!empty($data['username'])) $user->setUsername($data['username']);
        if (isset($data['telephone'])) $user->setTelephone($data['telephone'] ?: null);
        if (isset($data['email'])) $user->setEmail($data['email'] ?: null);
        if (isset($data['specialite'])) $user->setSpecialite($data['specialite'] ?: null);
        if (!empty($data['role'])) $user->setRoles([$data['role']]);
        $user->setActif(isset($data['actif']) && $data['actif'] === '1');
    }

    private function hydrateActe(ActeMedical $acte, array $data): void
    {
        if (!empty($data['libelle'])) $acte->setLibelle($data['libelle']);
        if (!empty($data['type'])) $acte->setType($data['type']);
        if (isset($data['categorie'])) $acte->setCategorie($data['categorie'] ?: null);
        if (!empty($data['prix_normal'])) $acte->setPrixNormal($data['prix_normal']);
        if (isset($data['prix_prise_en_charge'])) $acte->setPrixPriseEnCharge($data['prix_prise_en_charge'] ?: null);
        if (isset($data['description'])) $acte->setDescription($data['description'] ?: null);
        $acte->setActif(isset($data['actif']) && $data['actif'] === '1');
    }
}
