<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
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

class ProductController extends AbstractController
{
    /**
     * This method allows to recover all products.
     *
     * @OA\Response(
     *     response=200,
     *     description="Returns the list of products",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=User::class))
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
     * @OA\Tag(name="Products")
     *
     * @param ProductRepository $ProductRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('api/products', name: 'app_product', methods: ['GET'])]
    public function getProductList(ProductRepository $productRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 1);

        $idCache = "getAllProducts-" . $page . "-" . $limit;

        $jsonProductList = $cachePool->get($idCache, function (ItemInterface $item) use ($productRepository, $page, $limit, $serializer) {
            $item->tag("productsCache");
            $productList = $productRepository->findAllWithPagination($page, $limit);
            $context = SerializationContext::create();
            return  $serializer->serialize($productList, 'json', $context);
        });
        return new JsonResponse($jsonProductList, Response::HTTP_OK, [], true);
    }

    /**
     * This method retrieves the details of a product.
     *
     * @OA\Response(
     *     response=200,
     *     description="Return the product",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=Product::class))
     *     )
     * )
     * @OA\Tag(name="Products")
     *
     * @param ProductRepository $ProductRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/api/products/{id}', name: 'detailProduct', methods: ['GET'])]
    public function getDetailProduct(int $id, SerializerInterface $serializer, ProductRepository $productRepository): JsonResponse
    {
        $product = $productRepository->find($id);
        if ($product) {
            $context = SerializationContext::create();
            $jsonProduct = $serializer->serialize($product, 'json', $context);
            return new JsonResponse($jsonProduct, Response::HTTP_OK, [], true);
        }
        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    /**
     * This method creates a product.
     * 
     * @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         @OA\Property(property="name", type="string", example="Apple"),
     *         @OA\Property(property="description", description="The description of the new product.", type="string", example="it's great product"),
     *         @OA\Property(property="price", description="The price of the new product.", type="int", example="100"),
     *       )
     *     )
     *   ),
     * @OA\Response(
     *     response=200,
     *     description="create product",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=Product::class))
     *     )
     * )
     * @OA\Tag(name="Products")
     *
     * @param ProductRepository $ProductRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/api/products', name: "createProduct", methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour créer le produit')]
    public function createProduct(Request $request, SerializerInterface $serializer, EntityManagerInterface $entityManager, UrlGeneratorInterface $urlGenerator): JsonResponse
    {
        $product = $serializer->deserialize($request->getContent(), Product::class, 'json');
        $created_at = new DateTime();
        $updated_at = new DateTime();
        $product->setCreatedAt($created_at);
        $product->setUpdatedAt($updated_at);
        $entityManager->persist($product);
        $entityManager->flush();

        $context = SerializationContext::create();

        $jsonProduct = $serializer->serialize($product, 'json', $context);
        $location = $urlGenerator->generate('detailProduct', ['id' => $product->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonProduct, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    /**
     * This method allows to modify a product.
     * 
     * @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         @OA\Property(property="name", type="string", example="Apple"),
     *         @OA\Property(property="description", description="The description of the new product.", type="string", example="it's great product"),
     *         @OA\Property(property="price", description="The price of the new product.", type="int", example="100"),
     *       )
     *     )
     *   ),     * @OA\Response(
     *     response=200,
     *     description="update product",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=Product::class))
     *     )
     * )
     * @OA\Tag(name="Products")
     *
     * @param ProductRepository $ProductRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/api/products/{id}', name: "updateProduct", methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour modifier le produit')]
    public function updateProduct(Request $request, SerializerInterface $serializer, Product $currentProduct, EntityManagerInterface $entityManager, ValidatorInterface $validator, TagAwareCacheInterface $cache): JsonResponse
    {
        $updatedProduct = $serializer->deserialize($request->getContent(), Product::class, 'json');
        $currentProduct->setName($updatedProduct->getName());
        $currentProduct->setDescription($updatedProduct->getDescription());
        $currentProduct->setPrice($updatedProduct->getPrice());
        $updated_at = new DateTime();
        $currentProduct->setUpdatedAt($updated_at);
        // On vérifie les erreurs
        $errors = $validator->validate($currentProduct);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $entityManager->persist($currentProduct);
        $entityManager->flush();
        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * This method removes a product.
     *
     * @OA\Response(
     *     response=200,
     *     description="delete product",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=Product::class))
     *     )
     * )
     * @OA\Tag(name="Products")
     *
     * @param ProductRepository $ProductRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/api/products/{id}', name: 'deleteProduct', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour supprimer le produit')]
    public function deleteProduct(Product $product, EntityManagerInterface $entityManager, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $cachePool->invalidateTags(["productsCache"]);

        $entityManager->remove($product);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
