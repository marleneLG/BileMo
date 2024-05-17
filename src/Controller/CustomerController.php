<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Repository\CustomerRepository;
use App\Repository\UserRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CustomerController extends AbstractController
{
    #[Route('api/customers', name: 'app_customer', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour voir les clients')]
    public function getCustomerList(CustomerRepository $customerRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 1);

        $idCache = "getAllCustomers-" . $page . "-" . $limit;

        $jsonCustomerList = $cachePool->get($idCache, function (ItemInterface $item) use ($customerRepository, $page, $limit, $serializer) {
            $item->tag("customersCache");
            $customerList = $customerRepository->findAllWithPagination($page, $limit);
            $context = SerializationContext::create()->setGroups(['customer:read']);
            return  $serializer->serialize($customerList, 'json', $context);
        });
        return new JsonResponse($jsonCustomerList, Response::HTTP_OK, [], true);
    }

    #[Route('/api/customers', name: "createCustomer", methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour créer les clients')]
    public function createCustomer(Request $request, SerializerInterface $serializer, EntityManagerInterface $entityManager, UrlGeneratorInterface $urlGenerator, UserRepository $userRepository): JsonResponse
    {

        $customer = $serializer->deserialize($request->getContent(), Customer::class, 'json');

        // Récupération de l'ensemble des données envoyées sous forme de tableau
        $content = $request->toArray();

        // Récupération de l'idUser. S'il n'est pas défini, alors on met -1 par défaut.
        $idUser = $content['idUser'] ?? -1;
        $created_at = new DateTime();
        $updated_at = new DateTime();
        $customer->setCreatedAt($created_at);
        $customer->setUpdatedAt($updated_at);
        // On cherche le user qui correspond et on l'assigne au customer.
        // Si "find" ne trouve pas le user, alors null sera retourné.
        if ($idUser) {
            $customer->addUser($userRepository->find($idUser));
        }

        $entityManager->persist($customer);
        $entityManager->flush();

        $context = SerializationContext::create()->setGroups(['customer:read']);

        $jsonCustomer = $serializer->serialize($customer, 'json', $context);
        $location = $urlGenerator->generate('detailCustomer', ['id' => $customer->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonCustomer, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    #[Route('/api/customers/{id}', name: "updateCustomer", methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour modifier les clients')]
    public function updateCustomer(Request $request, SerializerInterface $serializer, Customer $currentCustomer, EntityManagerInterface $entityManager, UserRepository $userRepository, ValidatorInterface $validator, TagAwareCacheInterface $cache): JsonResponse
    {
        $updatedCustomer = $serializer->deserialize($request->getContent(), Customer::class, 'json');
        $currentCustomer->setName($updatedCustomer->getName());
        $currentCustomer->setEmail($updatedCustomer->getEmail());
        $currentCustomer->setRoles($updatedCustomer->getRoles());
        $updated_at = new DateTime();
        $currentCustomer->setUpdatedAt($updated_at);
        // On vérifie les erreurs
        $errors = $validator->validate($currentCustomer);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $content = $request->toArray();
        $idUser = $content['idUser'] ?? -1;
        $currentCustomer->addUser($userRepository->find($idUser));

        $entityManager->persist($currentCustomer);
        $entityManager->flush();
        $cache->invalidateTags(["usersCache"]);
        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route('/api/customers/{id}', name: 'detailCustomer', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour voir le client')]
    public function getDetailCustomer(int $id, SerializerInterface $serializer, CustomerRepository $customerRepository): JsonResponse
    {

        $customer = $customerRepository->find($id);
        if ($customer) {
            $context = SerializationContext::create()->setGroups(['customer:read']);
            $jsonCustomer = $serializer->serialize($customer, 'json', $context);
            return new JsonResponse($jsonCustomer, Response::HTTP_OK, [], true);
        }
        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route('/api/customers/{id}', name: 'deleteCustomer', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour supprimer les clients')]
    public function deleteCustomer(Customer $customer, EntityManagerInterface $entityManager, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $cachePool->invalidateTags(["customersCache"]);

        $entityManager->remove($customer);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
