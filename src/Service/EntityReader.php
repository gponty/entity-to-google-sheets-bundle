<?php

namespace Gponty\EntityToGoogleSheetsBundle\Service;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Mapping\ClassMetadata;
use Gponty\EntityToGoogleSheetsBundle\Attribute\SheetDescription;

class EntityReader
{
    public function __construct(
        private readonly ManagerRegistry $doctrine
    ) {}

    public function getAllEntities(): array
    {
        $result = [];
        $em = $this->doctrine->getManager();
        $metadatas = $em->getMetadataFactory()->getAllMetadata();

        foreach ($metadatas as $metadata) {
            /** @var ClassMetadata $metadata */
            $className = $metadata->getName();
            $tableName = $metadata->getTableName();

            $classRef = new \ReflectionClass($className);
            $classDescription = $this->getAttributeDescription($classRef);

            $fields = [];

            foreach ($metadata->getFieldNames() as $fieldName) {
                $mapping = $metadata->getFieldMapping($fieldName);
                $fieldDescription = '';

                if ($classRef->hasProperty($fieldName)) {
                    $fieldDescription = $this->getAttributeDescription(
                        $classRef->getProperty($fieldName)
                    );
                }

                $fields[] = [
                    'name'        => $fieldName,
                    'column'      => $mapping['columnName'] ?? $fieldName,
                    'type'        => $mapping['type'] ?? 'unknown',
                    'nullable'    => isset($mapping['nullable']) && $mapping['nullable'] ? 'Oui' : 'Non',
                    'length'      => $mapping['length'] ?? '',
                    'unique'      => isset($mapping['unique']) && $mapping['unique'] ? 'Oui' : 'Non',
                    'id'          => in_array($fieldName, $metadata->getIdentifierFieldNames()) ? 'Oui' : 'Non',
                    'description' => $fieldDescription,
                ];
            }

            foreach ($metadata->getAssociationMappings() as $assocName => $assocMapping) {
                $fieldDescription = '';

                if ($classRef->hasProperty($assocName)) {
                    $fieldDescription = $this->getAttributeDescription(
                        $classRef->getProperty($assocName)
                    );
                }

                $fields[] = [
                    'name'        => $assocName,
                    'column'      => $assocMapping['joinColumns'][0]['name'] ?? '(relation)',
                    'type'        => $this->getRelationType($assocMapping['type']),
                    'nullable'    => 'N/A',
                    'length'      => '',
                    'unique'      => 'N/A',
                    'id'          => 'Non',
                    'description' => $fieldDescription,
                ];
            }

            $result[$className] = [
                'tableName'   => $tableName,
                'description' => $classDescription,
                'fields'      => $fields,
            ];
        }

        uasort($result, fn($a, $b) => strcmp($a['tableName'], $b['tableName']));

        return $result;
    }

    private function getAttributeDescription(\ReflectionClass|\ReflectionProperty $ref): string
    {
        $attributes = $ref->getAttributes(SheetDescription::class);
        if (!empty($attributes)) {
            return $attributes[0]->newInstance()->description;
        }
        return '';
    }

    private function getRelationType(int $type): string
    {
        return match($type) {
            1 => 'OneToOne',
            2 => 'ManyToOne',
            4 => 'OneToMany',
            8 => 'ManyToMany',
            default => 'Relation'
        };
    }
}