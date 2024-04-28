<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Repository\CustomerRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

class CustomerController extends AbstractController
{
    #[Route('/api/customers', name: "createCustomer", methods: ['POST'])]
    public function createCustomer(Request $request, SerializerInterface $serializer, EntityManagerInterface $entityManager, UrlGeneratorInterface $urlGenerator, UserRepository $userRepository): JsonResponse
    {

        $customer = $serializer->deserialize($request->getContent(), Customer::class, 'json');

        // Récupération de l'ensemble des données envoyées sous forme de tableau
        $content = $request->toArray();

        // Récupération de l'idUser. S'il n'est pas défini, alors on met -1 par défaut.
        $idUser = $content['idUser'] ?? -1;

        // On cherche le user qui correspond et on l'assigne au customer.
        // Si "find" ne trouve pas le user, alors null sera retourné.
        $customer->addUser($userRepository->find($idUser));

        $entityManager->persist($customer);
        $entityManager->flush();

        $jsonCustomer = $serializer->serialize($customer, 'json', ['groups' => 'getCustomers']);

        $location = $urlGenerator->generate('detailCustomer', ['id' => $customer->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonCustomer, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    #[Route('/api/customers/{id}', name: "updateCustomer", methods: ['PUT'])]

    public function updateCustomer(Request $request, SerializerInterface $serializer, Customer $currentCustomer, EntityManagerInterface $entityManager): JsonResponse
    {
        $updatedCustomer = $serializer->deserialize(
            $request->getContent(),
            Customer::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $currentCustomer]
        );

        $entityManager->persist($updatedCustomer);
        $entityManager->flush();
        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route('/api/products/{id}', name: 'detailCustomer', methods: ['GET'])]
    public function getDetailCustomer(int $id, SerializerInterface $serializer, CustomerRepository $customerRepository): JsonResponse
    {

        $customer = $customerRepository->find($id);
        if ($customer) {
            $jsonCustomer = $serializer->serialize($customer, 'json');
            return new JsonResponse($jsonCustomer, Response::HTTP_OK, [], true);
        }
        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route('/api/customers/{id}', name: 'deleteCustomer', methods: ['DELETE'])]
    public function deleteCustomer(Customer $customer, EntityManagerInterface $entityManager): JsonResponse
    {

        $entityManager->remove($customer);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
