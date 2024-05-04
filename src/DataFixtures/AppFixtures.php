<?php

namespace App\DataFixtures;

use App\Entity\Admin;
use App\Entity\Customer;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private $userPasswordHasher;

    public function __construct(UserPasswordHasherInterface $userPasswordHasher)
    {
        $this->userPasswordHasher = $userPasswordHasher;
    }

    public function load(ObjectManager $manager): void
    {

        // // Création d'un user "normal"
        $user = new User;
        $user->setFirstname('henry');
        $user->setLastname('dupont');
        $user->setEmail("user@bookapi.com");
        $user->setRoles(["ROLE_USER"]);
        $listUser[] = $user;
        $manager->persist($user);

        // Création d'un user admin
        $userAdmin = new Admin;
        $userAdmin->setFirstname('Marlene');
        $userAdmin->setLastname('Admin');
        $userAdmin->setEmail("admin@bookapi.com");
        $userAdmin->setRoles(["ROLE_ADMIN"]);
        $userAdmin->setPassword($this->userPasswordHasher->hashPassword($userAdmin, "password"));
        $manager->persist($userAdmin);

        // // Création d'une vingtaine de User
        // for ($i = 0; $i < 5; $i++) {
        //     $user = new User;
        //     $user->setFirstname('Firstname : ' . $i);
        //     $user->setLastname('Lastname : ' . $i);
        //     $user->setEmail('Email : ' . $i);
        //     $user->setPassword('Password : ' . $i);
        //     $listUser[] = $user;
        //     $manager->persist($user);
        // }

        // Création d'une vingtaine de Client
        for ($i = 0; $i < 20; $i++) {
            $customer = new Customer;
            $customer->setName('Name : ' . $i);
            $customer->setEmail('Email : ' . $i);
            $customer->setPassword('Password : ' . $i);
            $customer->addUser($listUser[array_rand($listUser)]);
            $manager->persist($customer);
        }

        // Création d'une vingtaine de Product
        for ($i = 0; $i < 20; $i++) {
            $product = new Product;
            $product->setName('Name : ' . $i);
            $product->setDescription('Description : ' . $i);
            $product->setPrice($i);
            $manager->persist($product);
        }

        $manager->flush();
    }
}
