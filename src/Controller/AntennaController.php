<?php

namespace App\Controller;

use App\Entity\Antenna;
use App\Entity\User;
use App\Repository\AntennaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/antennes')]
#[IsGranted('ROLE_CLIENT_MANAGER')]
final class AntennaController extends AbstractController
{
    #[Route('', name: 'app_antennas')]
    public function list(AntennaRepository $antennas): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->render('antenna/list.html.twig', [
            'antennas' => $antennas->findBy(['company' => $user->getCompany()], ['name' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'app_antenna_new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        return $this->handleForm(new Antenna(), $request, $em);
    }

    #[Route('/{id}/edit', name: 'app_antenna_edit')]
    public function edit(Antenna $antenna, Request $request, EntityManagerInterface $em): Response
    {
        $this->assertOwns($antenna);
        return $this->handleForm($antenna, $request, $em);
    }

    #[Route('/{id}/delete', name: 'app_antenna_delete', methods: ['POST'])]
    public function delete(Antenna $antenna, EntityManagerInterface $em): RedirectResponse
    {
        $this->assertOwns($antenna);
        $em->remove($antenna);
        $em->flush();
        $this->addFlash('success', sprintf('Antenne "%s" supprimée.', $antenna->getName()));
        return $this->redirectToRoute('app_antennas');
    }

    private function handleForm(Antenna $antenna, Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $isNew = $antenna->getId() === null;
        $errors = [];

        if ($request->isMethod('POST')) {
            $name = trim((string) $request->request->get('name', ''));
            $address = trim((string) $request->request->get('address_line', ''));
            $postalCode = trim((string) $request->request->get('postal_code', ''));
            $city = trim((string) $request->request->get('city', ''));
            $phone = trim((string) $request->request->get('phone', ''));

            if ($name === '') $errors['name'] = 'Nom requis.';
            if ($address === '') $errors['address_line'] = 'Adresse requise.';
            if ($postalCode === '') $errors['postal_code'] = 'Code postal requis.';
            if ($city === '') $errors['city'] = 'Ville requise.';

            if (empty($errors)) {
                $antenna
                    ->setName($name)
                    ->setAddressLine($address)
                    ->setPostalCode($postalCode)
                    ->setCity($city)
                    ->setPhone($phone ?: null);
                if ($isNew) {
                    $antenna->setCompany($user->getCompany());
                    $em->persist($antenna);
                }
                $em->flush();
                $this->addFlash('success', $isNew ? 'Antenne créée.' : 'Antenne mise à jour.');
                return $this->redirectToRoute('app_antennas');
            }
        }

        return $this->render('antenna/form.html.twig', [
            'antenna' => $antenna,
            'is_new' => $isNew,
            'errors' => $errors,
        ]);
    }

    private function assertOwns(Antenna $antenna): void
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($antenna->getCompany()->getId() !== $user->getCompany()?->getId()) {
            throw $this->createAccessDeniedException();
        }
    }
}
