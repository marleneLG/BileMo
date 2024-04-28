<?php

namespace App\DataFixtures;

use App\Entity\Customer;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Création d'une vingtaine de User
        for ($i = 0; $i < 20; $i++) {
            $user = new User;
            $user->setFirstname('Firstname : ' . $i);
            $user->setLastname('Lastname : ' . $i);
            $user->setEmail('Email : ' . $i);
            $user->setPassword('Password : ' . $i);
            $listUser[] = $user;
            $manager->persist($user);
        }

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
