<?php

namespace App\Controller;

use App\Entity\User;
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
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;


class UserController extends AbstractController
{
    #[Route('/api/users', name: 'app_user', methods: ['GET'])]
    #[IsGranted('ROLE_USER', message: 'Vous n\'avez pas les droits suffisants pour voir les user')]
    public function getUserList(UserRepository $userRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 1);

        $idCache = "getAllUsers-" . $page . "-" . $limit;

        $jsonUserList = $cachePool->get($idCache, function (ItemInterface $item) use ($userRepository, $page, $limit, $serializer) {
            $item->tag("usersCache");
            $userList = $userRepository->findAllWithPagination($page, $limit);
            return  $serializer->serialize($userList, 'json', ['groups' => ['customer:read']]);
        });
        return new JsonResponse($jsonUserList, Response::HTTP_OK, [], true);
    }

    #[Route('/api/users/{id}', name: 'detailUser', methods: ['GET'])]
    #[IsGranted('ROLE_USER', message: 'Vous n\'avez pas les droits suffisants pour voir un user')]
    public function getDetailUser(int $id, SerializerInterface $serializer, UserRepository $userRepository): JsonResponse
    {

        $user = $userRepository->find($id);
        if ($user) {
            $jsonUser = $serializer->serialize($user, 'json', ['groups' => ['customer:read']]);
            return new JsonResponse($jsonUser, Response::HTTP_OK, [], true);
        }
        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[IsGranted('ROLE_USER', message: 'Vous n\'avez pas les droits suffisants pour créer un user')]
    #[Route('/api/users', name: "createUser", methods: ['POST'])]
    public function createUser(Request $request, SerializerInterface $serializer, EntityManagerInterface $entityManager, UrlGeneratorInterface $urlGenerator, CustomerRepository $customerRepository): JsonResponse
    {
        $user = $serializer->deserialize($request->getContent(), User::class, 'json');

        // Récupération de l'ensemble des données envoyées sous forme de tableau
        $content = $request->toArray();

        // Récupération de l'idCustomer. S'il n'est pas défini, alors on met -1 par défaut.
        $idCustomer = $content['idCustomer'] ?? -1;

        // On cherche customer qui correspond et on l'assigne au user.
        // Si "find" ne trouve pas customer, alors null sera retourné.
        $user->addCustomer($customerRepository->find($idCustomer));

        $entityManager->persist($user);
        $entityManager->flush();

        $jsonUser = $serializer->serialize($user, 'json', ['groups' => 'getCustomers']);

        $location = $urlGenerator->generate('detailUser', ['id' => $user->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonUser, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    #[Route('/api/users/{id}', name: "updateUser", methods: ['PUT'])]
    #[IsGranted('ROLE_USER', message: 'Vous n\'avez pas les droits suffisants pour modifier un user')]
    public function updateUser(Request $request, SerializerInterface $serializer, User $currentUser, EntityManagerInterface $entityManager): JsonResponse
    {
        $updatedUser = $serializer->deserialize(
            $request->getContent(),
            User::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $currentUser]
        );

        $entityManager->persist($updatedUser);
        $entityManager->flush();
        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route('/api/users/{id}', name: 'deleteUser', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER', message: 'Vous n\'avez pas les droits suffisants pour supprimer un user')]
    public function deleteUser(User $user, EntityManagerInterface $entityManager, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $cachePool->invalidateTags(["usersCache"]);

        $entityManager->remove($user);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
