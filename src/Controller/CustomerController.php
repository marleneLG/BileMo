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
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Annotations as OA;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;


class CustomerController extends AbstractController
{
    /**
     * This method allows to recover all customers.
     *
     * @OA\Response(
     *     response=200,
     *     description="Returns the list of customers",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=Customer::class, groups={"customer:read"}))
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
     * @OA\Tag(name="Customers")
     *
     * @param CustomerRepository $CustomerRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('api/customers', name: 'app_customer', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN', message: 'You do not have sufficient rights to see customers')]
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

    /**
     * This method retrieves the details of a customer.
     *
     * @OA\Response(
     *     response=200,
     *     description="Return the customer",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=Customer::class, groups={"customer:read"}))
     *     )
     * )
     * 
     * @OA\Response(response=404, description="This customer doesn't exist")
     * @OA\Response(response=401, description="Expired JWT Token")
     * 
     * @OA\Tag(name="Customers")
     *
     * @param CustomerRepository $CustomerRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/api/customers/{id}', name: 'detailCustomer', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN', message: 'You do not have sufficient rights to see the customer')]
    public function getDetailCustomer(int $id, SerializerInterface $serializer, CustomerRepository $customerRepository): JsonResponse
    {
        $customer = $customerRepository->find($id);
        if ($customer === null) {
            return $this->json([
                'status' => 404,
                'message' => "This customer doesn't exist"
            ], 404);
        }
        if ($customer) {
            $context = SerializationContext::create()->setGroups(['customer:read']);
            $jsonCustomer = $serializer->serialize($customer, 'json', $context);
            return new JsonResponse($jsonCustomer, Response::HTTP_OK, [], true);
        }
        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    /**
     * This method creates a customer.
     *
     * @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         @OA\Property(property="name", type="string", example="John"),
     *         @OA\Property(property="password", description="The password of the new customer.", type="string", example="password"),
     *         @OA\Property(property="idUser", description="The idUser of the new customer.", type="int", example="1"),
     *         @OA\Property(property="email", description="Email address of the new customer.", type="string", format="email", example="j.doe91@yopmail.fr")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=201,
     *     description="Customer created",
     *   )
     * 
     *   @OA\Response(
     *     response=401,
     *     description="JWT erreur de token"
     *   )
     *  
     * )
     *   @OA\Tag(name="Customers")
     * 
     * @param CustomerRepository $CustomerRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @return JsonResponse 
     */
    #[IsGranted('ROLE_ADMIN', message: 'You do not have sufficient rights to create customers')]
    #[Route('/api/customers', name: "createCustomer", methods: ['POST'])]
    public function createCustomer(Request $request, SerializerInterface $serializer, EntityManagerInterface $entityManager, UrlGeneratorInterface $urlGenerator, UserPasswordHasherInterface $customerPassword): JsonResponse
    {

        $customer = $serializer->deserialize($request->getContent(), Customer::class, 'json');

        $created_at = new DateTime();
        $updated_at = new DateTime();
        $customer->setCreatedAt($created_at);
        $customer->setUpdatedAt($updated_at);
        $customer->setRoles(array('ROLE_USER'));
        $customer->setPassword($customerPassword->hashPassword($customer, $customer->getPassword()));

        $entityManager->persist($customer);
        $entityManager->flush();

        $context = SerializationContext::create()->setGroups(['customer:read']);

        $jsonCustomer = $serializer->serialize($customer, 'json', $context);
        $location = $urlGenerator->generate('detailCustomer', ['id' => $customer->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonCustomer, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    /**
     * This method allows to modify a customer.
     * 
     * @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         @OA\Property(property="name", type="string", example="John"),
     *         @OA\Property(property="password", description="The password of the new customer.", type="string", example="password"),
     *         @OA\Property(property="email", description="Email address of the new customer.", type="string", format="email", example="j.doe91@yopmail.fr")
     *       )
     *     )
     *   ),
     * @OA\Response(
     *     response=200,
     *     description="update customer",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=Customer::class, groups={"customer:read"}))
     *     )
     * )
     * 
     * @OA\Response(response=401, description="Expired JWT Token")
     * 
     * @OA\Response(response=404, description="This customer doesn't exist")
     * 
     * @OA\Tag(name="Customers")
     *
     * @param CustomerRepository $CustomerRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/api/customers/{id}', name: "updateCustomer", methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: 'You do not have sufficient rights to modify customers')]
    public function updateCustomer(Request $request, SerializerInterface $serializer, Customer $currentCustomer, EntityManagerInterface $entityManager, UserPasswordHasherInterface $customerPassword, ValidatorInterface $validator, TagAwareCacheInterface $cache): JsonResponse
    {
        if ($currentCustomer === null) {
            return $this->json([
                'status' => 404,
                'message' => "This customer doesn't exist"
            ], 404);
        }
        $updatedCustomer = $serializer->deserialize($request->getContent(), Customer::class, 'json');
        $currentCustomer->setName($updatedCustomer->getName());
        $currentCustomer->setEmail($updatedCustomer->getEmail());
        $currentCustomer->setRoles($updatedCustomer->getRoles());
        $currentCustomer->setPassword($customerPassword->hashPassword($currentCustomer, $currentCustomer->getPassword()));

        $updated_at = new DateTime();
        $currentCustomer->setUpdatedAt($updated_at);
        // On vérifie les erreurs
        $errors = $validator->validate($currentCustomer);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $entityManager->persist($currentCustomer);
        $entityManager->flush();
        $cache->invalidateTags(["usersCache"]);
        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * This method removes a customer.
     *
     * @OA\Response(
     *     response=200,
     *     description="delete customer",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=Customer::class, groups={"customer:read"}))
     *     )
     * )
     * 
     * @OA\Response(response=401, description="Expired JWT Token")
     * 
     * @OA\Response(response=404, description="This customer doesn't exist")
     * 
     * @OA\Tag(name="Customers")
     *
     * @param CustomerRepository $CustomerRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/api/customers/{id}', name: 'deleteCustomer', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: 'You do not have sufficient rights to delete customers')]
    public function deleteCustomer(Customer $customer, EntityManagerInterface $entityManager, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $cachePool->invalidateTags(["customersCache"]);
        if ($customer === null) {
            return $this->json([
                'status' => 404,
                'message' => "This customer doesn't exist"
            ], 404);
        }
        $entityManager->remove($customer);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
