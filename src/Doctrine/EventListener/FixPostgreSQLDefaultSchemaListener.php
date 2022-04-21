<?php

declare(strict_types=1);

namespace App\Doctrine\EventListener;

use Doctrine\DBAL\Schema\PostgreSqlSchemaManager;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

final class FixPostgreSQLDefaultSchemaListener
{
    /**
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    public function postGenerateSchema(GenerateSchemaEventArgs $args): void
    {
        $schemaManager = $args
            ->getEntityManager()
            ->getConnection()
            ->getSchemaManager()
        ;

        if (!$schemaManager instanceof PostgreSqlSchemaManager) {
            return;
        }

        $schema = $args->getSchema();

        foreach ($schemaManager->listSchemaNames() as $namespace) {
            if (!$schema->hasNamespace($namespace)) {
                $schema->createNamespace($namespace);
            }
        }
    }
}

// TODO Не понял, куда это добавить

// services_dev.php 


// <?php

// declare(strict_types=1);

// use App\Doctrine\EventListener\FixPostgreSQLDefaultSchemaListener;
// use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

// return static function (ContainerConfigurator $configurator): void {
//     $services = $configurator->services();

//     $services
//         ->set(FixPostgreSQLDefaultSchemaListener::class)
//         ->tag('doctrine.event_listener', ['event' => 'postGenerateSchema'])
//     ;
// };