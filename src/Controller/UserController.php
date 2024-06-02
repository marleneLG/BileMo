<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\User;
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
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Annotations as OA;

class UserController extends AbstractController
{
    /**
     * This method allows to recover all users.
     *
     * @OA\Response(
     *     response=200,
     *     description="Returns the list of users",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=User::class, groups={"customer:read"}))
     *     )
     * )
     * @OA\Parameter(
     *     name="page",
     *     in="query",
     *     description="The page you want to retrieve",
     *     @OA\Schema(type="int")
     * )
     *
     * @OA\Parameter(
     *     name="limit",
     *     in="query",
     *     description="The number of items you want to recover",
     *     @OA\Schema(type="int")
     * )
     * 
     * @OA\Response(response=401, description="Expired JWT Token")
     * 
     * @OA\Tag(name="Users")
     *
     * @param UserRepository $UserRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/api/users', name: 'app_user', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN', message: 'You do not have sufficient rights to see them use')]
    public function getUserList(UserRepository $userRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 1);

        $idCache = "getAllUsers-" . $page . "-" . $limit;

        $jsonUserList = $cachePool->get($idCache, function (ItemInterface $item) use ($userRepository, $page, $limit, $serializer) {
            $item->tag("usersCache");
            $userList = $userRepository->findAllWithPagination($page, $limit);
            $context = SerializationContext::create()->setGroups(['customer:read']);

            return  $serializer->serialize($userList, 'json', $context);
        });
        return new JsonResponse($jsonUserList, Response::HTTP_OK, [], true);
    }

    /**
     * This method retrieves the details of a user.
     *
     * @OA\Response(
     *     response=200,
     *     description="Return the user",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=User::class, groups={"customer:read"}))
     *     )
     * )
     * 
     * @OA\Response(response=404, description="This user doesn't exist")
     * 
     * @OA\Response(response=403, description="Not authorized to see this user")
     * 
     * @OA\Response(response=401, description="Expired JWT Token")
     * 
     * @OA\Tag(name="Users")
     *
     * @param UserRepository $UserRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/api/users/{id}', name: 'detailUser', methods: ['GET'])]
    #[IsGranted('ROLE_USER', message: 'You do not have sufficient rights to see a user')]
    public function getDetailUser(int $id, SerializerInterface $serializer, UserRepository $userRepository): JsonResponse
    {

        $user = $userRepository->find($id);
        if ($user->getCustomer() !== $this->getUser()) {
            return $this->json([
                'status' => 403,
                'message' => "Not authorized to see this user."
            ], 403);
        }
        if ($user === null) {
            return $this->json([
                'status' => 404,
                'message' => "This user doesn't exist"
            ], 404);
        }

        if ($user) {
            $context = SerializationContext::create()->setGroups(['customer:read']);
            $jsonUser = $serializer->serialize($user, 'json', $context);
            return new JsonResponse($jsonUser, Response::HTTP_OK, [], true);
        }
        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    /**
     * This method creates a user.
     *
     * @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         @OA\Property(property="firstname", type="string", example="John"),
     *         @OA\Property(property="lastname", description="The lastname of the new user.", type="string", example="Doe"),
     *         @OA\Property(property="email", description="Email address of the new user.", type="string", format="email", example="j.doe91@yopmail.fr")
     *       )
     *     )
     *   ),
     * @OA\Response(
     *     response=200,
     *     description="user created",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=User::class, groups={"customer:read"}))
     *     )
     * )
     *   ),
     * 
     * @OA\Response(response=401, description="Expired JWT Token")
     *  
     * @OA\Tag(name="Users")
     *
     * @param UserRepository $UserRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @return JsonResponse
     */
    #[IsGranted('ROLE_USER', message: 'You do not have sufficient rights to create a user')]
    #[Route('/api/users', name: "createUser", methods: ['POST'])]
    public function createUser(Request $request, SerializerInterface $serializer, EntityManagerInterface $entityManager, UrlGeneratorInterface $urlGenerator): JsonResponse
    {
        $user = $serializer->deserialize($request->getContent(), User::class, 'json');

        $created_at = new DateTime();
        $updated_at = new DateTime();
        $user->setCreatedAt($created_at);
        $user->setUpdatedAt($updated_at);
        $user->setCustomer($this->getUser());
        $entityManager->persist($user);
        $entityManager->flush();

        $context = SerializationContext::create()->setGroups(['customer:read']);

        $jsonUser = $serializer->serialize($user, 'json', $context);

        $location = $urlGenerator->generate('detailUser', ['id' => $user->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonUser, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    /**
     * This method allows to modif a user.
     *
     * @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         @OA\Property(property="firstname", type="string", example="John"),
     *         @OA\Property(property="lastname", description="The lastname of the new user.", type="string", example="Doe"),
     *         @OA\Property(property="email", description="Email address of the new user.", type="string", format="email", example="j.doe91@yopmail.fr")
     *       )
     *     )
     *   ), 
     * @OA\Response(
     *     response=200,
     *     description="update user",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=User::class, groups={"customer:read"}))
     *     )
     * )
     * 
     * @OA\Response(response=404, description="This user doesn't exist")
     * 
     * @OA\Response(response=403, description="Not authorized to update this user")
     * 
     * @OA\Response(response=401, description="Expired JWT Token")
     * 
     * @OA\Tag(name="Users")
     *
     * @param UserRepository $UserRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/api/users/{id}', name: "updateUser", methods: ['PUT'])]
    #[IsGranted('ROLE_USER', message: 'You do not have sufficient rights to modify a user')]
    public function updateUser(Request $request, SerializerInterface $serializer, User $currentUser, CustomerRepository $customerRepository, EntityManagerInterface $entityManager, ValidatorInterface $validator, TagAwareCacheInterface $cache): JsonResponse
    {
        if ($currentUser->getCustomer() !== $this->getUser()) {
            return $this->json([
                'status' => 403,
                'message' => "Not authorized to update this user."
            ], 403);
        }
        if ($currentUser === null) {
            return $this->json([
                'status' => 404,
                'message' => "This user doesn't exist"
            ], 404);
        }
        $updatedUser = $serializer->deserialize($request->getContent(), User::class, 'json');
        $currentUser->setFirstname($updatedUser->getFirstname());
        $currentUser->setLastname($updatedUser->getLastname());
        $currentUser->setEmail($updatedUser->getEmail());
        $updated_at = new DateTime();
        $currentUser->setUpdatedAt($updated_at);
        // On vérifie les erreurs
        $errors = $validator->validate($currentUser);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $entityManager->persist($currentUser);
        $entityManager->flush();

        $cache->invalidateTags(["usersCache"]);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * This method removes a user.
     *
     * @OA\Response(
     *     response=200,
     *     description="delete user",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=User::class, groups={"customer:read"}))
     *     )
     * )
     * 
     * @OA\Response(response=404, description="This user doesn't exist")
     * 
     * @OA\Response(response=403, description="Not authorized to delete this user")
     * 
     * @OA\Response(response=401, description="Expired JWT Token")
     * 
     * @OA\Tag(name="Users")
     *
     * @param UserRepository $UserRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/api/users/{id}', name: 'deleteUser', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER', message: 'You do not have sufficient rights to delete a user')]
    public function deleteUser(User $user, EntityManagerInterface $entityManager, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $cachePool->invalidateTags(["usersCache"]);
        if ($user->getCustomer() !== $this->getUser()) {
            return $this->json([
                'status' => 403,
                'message' => "Not authorized to delete this user."
            ], 403);
        }
        if ($user === null) {
            return $this->json([
                'status' => 404,
                'message' => "This user doesn't exist"
            ], 404);
        }
        $entityManager->remove($user);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
