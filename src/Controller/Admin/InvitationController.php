<?php

namespace App\Controller\Admin;

use App\Entity\Company;
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
use Symfony\Component\String\Slugger\SluggerInterface;
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
        SluggerInterface $slugger,
    ): Response {
        $errors = [];
        $email = (string) $request->request->get('email', '');
        $mode = (string) $request->request->get('company_mode', 'existing');
        $companyId = (int) ($request->request->get('company_id') ?? $request->query->get('company', 0));
        $newCompanyName = trim((string) $request->request->get('new_company_name', ''));
        $newCompanySiret = trim((string) $request->request->get('new_company_siret', ''));

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

            $company = null;
            if ($mode === 'new') {
                if ($newCompanyName === '') {
                    $errors['new_company_name'] = 'Nom de l\'entreprise requis.';
                } else {
                    $company = (new Company())
                        ->setName($newCompanyName)
                        ->setSlug(strtolower((string) $slugger->slug($newCompanyName)))
                        ->setSiret($newCompanySiret ?: null);
                    $em->persist($company);
                }
            } else {
                $company = $companyId > 0 ? $companies->find($companyId) : null;
                if (!$company) {
                    $errors['company'] = 'Entreprise requise.';
                }
            }

            if (empty($errors)) {
                $invitation = (new Invitation())
                    ->setEmail($email)
                    ->setCompany($company);
                $em->persist($invitation);
                $em->flush();

                $createdPrefix = $mode === 'new' ? sprintf('Entreprise « %s » créée. ', $company->getName()) : '';
                $this->addFlash('success', sprintf(
                    '%sInvitation envoyée à %s. Utilise « Copier le lien » pour le transmettre.',
                    $createdPrefix,
                    $email
                ));

                return $this->redirectToRoute('app_admin_invitations');
            }
        }

        return $this->render('admin/invitation/new.html.twig', [
            'companies' => $companies->findBy([], ['name' => 'ASC']),
            'errors' => $errors,
            'email' => $email,
            'company_id' => $companyId,
            'mode' => $mode,
            'new_company_name' => $newCompanyName,
            'new_company_siret' => $newCompanySiret,
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
