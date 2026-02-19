# Entity to Google Sheets Bundle

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

Un bundle Symfony puissant et simple pour exporter automatiquement les entités Doctrine vers des feuilles Google Sheets.

## Fonctionnalités

- 📊 **Export des entités Doctrine** : Exporte toutes vos entités Doctrine vers Google Sheets automatiquement
- 🔄 **Gestion complète des relations** : Support des relations OneToOne, OneToMany, ManyToOne et ManyToMany
- 📑 **Onglets organisés** : Crée automatiquement un onglet par entité avec des informations détaillées
- 🗂️ **Feuille d'index** : Génère une feuille d'index avec la liste complète de toutes les entités
- 🧹 **Nettoyage automatique** : Efface les données précédentes avant chaque export
- 💻 **Interface en ligne de commande** : Commande Symfony simple et intuitive
- 🔐 **Authentification Google** : Intégration sécurisée avec l'API Google Sheets

## Prérequis

- PHP >= 8.2
- Symfony Framework 7.0 ou 8.0
- Doctrine ORM >= 3.0
- Google API Client >= 2.15

## Installation

### 1. Installer via Composer

```bash
composer require gponty/entity-to-google-sheets-bundle
```

### 2. Enregistrer le bundle

Si vous n'êtes pas sur une version totalement récente de Symfony, enregistrez le bundle dans `config/bundles.php` :

```php
return [
    // ...
    TonVendor\EntityToGoogleSheetsBundle\EntityToGoogleSheetsBundle::class => ['all' => true],
];
```

### 3. Configurer les identifiants Google

#### Créer un projet Google Cloud

1. Allez sur [Google Cloud Console](https://console.cloud.google.com/)
2. Créez un nouveau projet
3. Activez l'API **Google Sheets API**
4. Créez un compte de service
5. Téléchargez le fichier de clés JSON

#### Ajouter une variable d'environnement

Dans votre fichier `.env` :

```env
GOOGLE_SHEETS_SPREADSHEET_ID=your_spreadsheet_id_here
GOOGLE_SHEETS_CREDENTIALS_PATH=%kernel.project_dir%/credentials/google-credentials.json
```

#### Partager le spreadsheet avec le compte de service

⚠️ **Étape importante** : Vous devez donner accès au Google Sheet au compte de service.

1. Ouvrez le fichier `google-credentials.json` que vous avez téléchargé
2. Trouvez le champ `"client_email"`, par exemple : `my-service-account@my-project.iam.gserviceaccount.com`
3. Allez sur votre [Google Sheet](https://sheets.google.com/)
4. Cliquez sur le bouton **"Partager"** en haut à droite
5. Collez l'email du compte de service (`client_email`)
6. Donnez les permissions **"Éditeur"** (Editor)
7. Cliquez sur **"Partager"**

Sans cette étape, le bundle ne pourra pas accéder au spreadsheet et l'export échouera avec une erreur d'authentification.

## Utilisation

### Exécuter l'export

```bash
php bin/console app:export-entities-to-sheets
```

Cette commande va :
1. Lire toutes les entités Doctrine enregistrées
2. Créer ou mettre à jour les onglets Google Sheets correspondants
3. Remplir chaque onglet avec les informations de l'entité
4. Générer une feuille d'index avec la liste complète

### Exemple de sortie

```
 Export des entités vers Google Sheets
 ====================================

 Lecture des entités Doctrine...
 15 entité(s) trouvée(s).

 Export vers Google Sheets...
 [████████████████████████] 100%

 ✓ Export terminé avec succès ! 🎉
```

## Bonnes pratiques

### Lancer la commande à chaque déploiement

🎯 **Conseil important** : Il est recommandé de lancer la commande à la fin de chaque déploiement pour s'assurer que votre Google Sheet reste toujours synchronisé avec votre structure de base de données.

#### Via un script de déploiement

Ajouter la commande dans votre script de déploiement (par exemple dans `deploy.sh`) :

```bash
#!/bin/bash
# ... autres commandes de déploiement ...

# Étapes classiques
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console cache:clear --env=prod

# ✨ Export automatique vers Google Sheets
php bin/console app:export-entities-to-sheets

echo "✓ Déploiement terminé !"
```

#### Via un hook Symfony (post-deploy)

Si vous utilisez Symfony Flex ou un orchestrateur (Capistrano, Deployer, etc.), intégrez la commande dans votre config :

```yaml
# config/services.yaml (exemple avec Capistrano)
deploy:
  post-deploy:
    - php bin/console app:export-entities-to-sheets
```

#### Via un job cron (mise à jour périodique)

Pour une mise à jour automatique périodique :

```bash
# Ajouter dans crontab
0 2 * * * cd /path/to/project && php bin/console app:export-entities-to-sheets >> /var/log/sheets-export.log 2>&1
```

Cela va exécuter l'export chaque nuit à 2h du matin.

## Structure des données exportées

### Chaque onglet d'entité contient :

| Colonne | Description |
|---------|-------------|
| **Nom du champ** | Nom de la propriété de l'entité |
| **Colonne DB** | Nom de la colonne en base de données |
| **Type** | Type de données (string, integer, datetime, etc.) |
| **Nullable** | Si le champ peut être nul |
| **Longueur** | Longueur maximale du champ (s'applicable) |
| **Unique** | Si le champ a une contrainte UNIQUE |
| **ID** | Si c'est la clé primaire |

### Feuille d'index

Une feuille d'index est créée avec :
- Liste de toutes les entités
- Tableau correspondant
- Nombre de champs par entité
- Dates de création et de modification

## Configuration avancée

### Services personnalisés

Le bundle enregistre automatiquement les services suivants :

- `entity_to_google_sheets.entity_reader` : Lecteur d'entités Doctrine
- `entity_to_google_sheets.sheets_exporter` : Exportateur Google Sheets

Vous pouvez les injecter dans vos propres services :

```php
use Gponty\EntityToGoogleSheetsBundle\Service\EntityReader;
use Gponty\EntityToGoogleSheetsBundle\Service\GoogleSheetsExporter;

class MonService
{
    public function __construct(
        private readonly EntityReader $entityReader,
        private readonly GoogleSheetsExporter $exporter,
    ) {}

    public function faireQuelquechose()
    {
        $entities = $this->entityReader->getAllEntities();
        $this->exporter->export($entities);
    }
}
```

## Gestion des erreurs

En cas d'erreur lors de l'authentification Google ou de l'accès au Spreadsheet :

```bash
# Vérifiez que le fichier credentials.json existe et est valide
# Vérifiez que le SPREADSHEET_ID est correct
# Vérifiez que le compte de service a accès au spreadsheet
```

## Architecture

### Classes principales

#### `EntityReader`
Lit les métadonnées de toutes les entités Doctrine enregistrées et extrait :
- Les noms des champs et leurs types
- Les colonnes de base de données
- Les relations (OneToOne, OneToMany, ManyToOne, ManyToMany)
- Les contraintes (nullable, unique, length)

#### `GoogleSheetsExporter`
Gère l'interaction avec l'API Google Sheets :
- Authentification via le compte de service
- Création/suppression d'onglets
- Insertion de données formatées
- Mise en forme des feuilles

#### `ExportEntitiesToSheetsCommand`
Commande Symfony orchestrant le processus complet avec une interface utilisateur conviviale.

## Support des types de données

Le bundle supporte les types Doctrine suivants :

- **Chaînes** : string, text
- **Numériques** : integer, smallint, bigint, decimal, float
- **Booléens** : boolean
- **Dates** : date, datetime, datetimetz, time
- **JSON** : json, json_array
- **Relations** : one_to_one, one_to_many, many_to_one, many_to_many

## Limitations

- Les données binaires (BLOB) ne sont pas exportées
- Les relations circulaires affichent uniquement le nom de la jointure
- Google Sheets a une limite d'environ 5 millions de cellules par feuille

## Performance

Pour les projets avec un grand nombre d'entités :

- L'export est optimisé pour minimiser les appels API Google
- Les métadonnées sont cachées en mémoire
- Le traitement est linéaire par rapport au nombre d'entités

## Dépannage

### L'export échoue avec "Unable to authenticate"
- Vérifiez que les identifiants Google sont corrects
- Assurez-vous que le compte de service a accès au spreadsheet
- Vérifiez que l'API Google Sheets est activée

### Aucune entité n'est trouvée
- Vérifiez que vos entités sont correctement mappées avec Doctrine
- Assurez-vous que le gestionnaire d'entités Doctrine est fonctionnel

### L'export s'arrête au bout d'un certain temps
- Ce peut être une limite de l'API Google. Attendez quelques minutes avant de réessayer

## Licence

Ce bundle est sous licence MIT. Voir [LICENSE](LICENSE) pour plus de détails.

## Contribution

Les contributions sont les bienvenues ! Pour contribuer :

1. Forkez le repository
2. Créez une branche pour votre feature (`git checkout -b feature/AmazingFeature`)
3. Commitez vos changements (`git commit -m 'Add some AmazingFeature'`)
4. Poussez vers la branche (`git push origin feature/AmazingFeature`)
5. Ouvrez une Pull Request

## Auteur

Développé par **gponty**

## Changelog

### Version 1.0.0
- ✨ Release initiale
- 📊 Support complet de l'export des entités Doctrine
- 🔄 Gestion des relations
- 📑 Génération automatique d'onglets
