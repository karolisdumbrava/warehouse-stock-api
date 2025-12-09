<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Client;
use App\Entity\Product;
use App\Entity\Warehouse;
use App\Entity\WarehouseStock;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Create Clients (API consumers)
        $vinted = new Client('Vinted', 'vinted-api-key-2025');
        $senukai = new Client('Senukai', 'senukai-api-key-2025');
        $testClient = new Client('Test Client', 'test-api-key');

        $manager->persist($vinted);
        $manager->persist($senukai);
        $manager->persist($testClient);

        // Create Warehouses
        $vilnius = new Warehouse('Vilnius Warehouse', 'Vilnius, Lithuania');
        $kaunas = new Warehouse('Kaunas Warehouse', 'Kaunas, Lithuania');
        $klaipeda = new Warehouse('Klaipėda Warehouse', 'Klaipėda, Lithuania');

        $manager->persist($vilnius);
        $manager->persist($kaunas);
        $manager->persist($klaipeda);

        // Create Products
        $boxSmall = new Product('BOX-S', 'Small Shipping Box');
        $boxMedium = new Product('BOX-M', 'Medium Shipping Box');
        $boxLarge = new Product('BOX-L', 'Large Shipping Box');

        $manager->persist($boxSmall);
        $manager->persist($boxMedium);
        $manager->persist($boxLarge);

        // Vilnius - main hub
        $manager->persist(new WarehouseStock($vilnius, $boxSmall, 100));
        $manager->persist(new WarehouseStock($vilnius, $boxMedium, 50));
        $manager->persist(new WarehouseStock($vilnius, $boxLarge, 30));

        // Kaunas
        $manager->persist(new WarehouseStock($kaunas, $boxSmall, 40));
        $manager->persist(new WarehouseStock($kaunas, $boxMedium, 80));

        // Klaipėda
        $manager->persist(new WarehouseStock($klaipeda, $boxSmall, 25));
        $manager->persist(new WarehouseStock($klaipeda, $boxLarge, 100));

        $manager->flush();
    }
}