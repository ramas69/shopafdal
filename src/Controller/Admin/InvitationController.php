<?php

namespace App\Controller\Admin;

use App\Entity\Invitation;
use App\Repository\CompanyRepository;
use App\Repository\InvitationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/admin/invitations')]
#[IsGranted('ROLE_ADMIN')]
final class InvitationController extends AbstractController
{
    #[Route('', name: 'app_admin_invitations')]
    public function list(InvitationRepository $invitations): Response
    {
        return $this->render('admin/invitation/list.html.twig', [
            'invitations' => $invitations->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'app_admin_invitation_new')]
    public function new(
        Request $request,
        CompanyRepository $companies,
        UserRepository $users,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
    ): Response {
        $errors = [];
        $email = (string) $request->request->get('email', '');
        $companyId = (int) $request->request->get('company_id', 0);

        if ($request->isMethod('POST')) {
            $emailViolations = $validator->validate($email, [
                new Assert\NotBlank(message: 'Email requis.'),
                new Assert\Email(message: 'Email invalide.'),
            ]);
            foreach ($emailViolations as $v) {
                $errors['email'] = $v->getMessage();
            }

            if ($users->findByEmail($email)) {
                $errors['email'] = 'Un utilisateur avec cet email existe déjà.';
            }

            $company = $companyId > 0 ? $companies->find($companyId) : null;
            if (!$company) {
                $errors['company'] = 'Entreprise requise.';
            }

            if (empty($errors)) {
                $invitation = (new Invitation())
                    ->setEmail($email)
                    ->setCompany($company);
                $em->persist($invitation);
                $em->flush();

                $url = $this->generateUrl('app_register', ['token' => $invitation->getToken()], 0);
                $this->addFlash('success', sprintf(
                    'Invitation créée. Lien à transmettre : %s%s',
                    $request->getSchemeAndHttpHost(),
                    $url
                ));

                return $this->redirectToRoute('app_admin_invitations');
            }
        }

        return $this->render('admin/invitation/new.html.twig', [
            'companies' => $companies->findBy([], ['name' => 'ASC']),
            'errors' => $errors,
            'email' => $email,
            'company_id' => $companyId,
        ]);
    }

    #[Route('/{id}/revoke', name: 'app_admin_invitation_revoke', methods: ['POST'])]
    public function revoke(Invitation $invitation, EntityManagerInterface $em): RedirectResponse
    {
        if ($invitation->isPending()) {
            $invitation->setRevokedAt(new \DateTimeImmutable());
            $em->flush();
            $this->addFlash('success', 'Invitation révoquée.');
        }
        return $this->redirectToRoute('app_admin_invitations');
    }
}
