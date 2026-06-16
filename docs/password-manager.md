# Module de gestion des mots de passe

## Prérequis

- PHP 8.2 ou supérieur avec l'extension Sodium ;
- Symfony 7.4 ;
- MySQL 8.4 ;
- une clé de coffre stable et conservée dans un gestionnaire de secrets.

## Installation

1. Définir les secrets dans `.env.local` ou dans le gestionnaire de secrets de la plateforme :

   ```dotenv
   APP_SECRET=<valeur-aleatoire>
   APP_VAULT_KEY=<cle-base64-de-32-octets>
   ```

   Génération possible :

   ```bash
   php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
   ```

2. Vérifier `DATABASE_URL`, créer la base si nécessaire, puis appliquer la migration :

   ```bash
   php bin/console doctrine:database:create --if-not-exists
   php bin/console doctrine:migrations:migrate
   ```

   Utiliser un compte MySQL dédié et le moteur InnoDB. Les identifiants réels doivent rester dans `.env.local` ou dans le gestionnaire de secrets de production.

3. Créer les modules de base et le premier super administrateur :

   ```bash
   php bin/console app:password-manager:install admin@example.com
   ```

   La commande demande le mot de passe dans une saisie masquée. En automatisation non interactive, il peut être fourni comme second argument depuis un secret CI.

4. Démarrer l'application et se connecter sur `/connexion`.

## Modèle de droits

- `ROLE_SUPER_ADMIN` dispose de tous les droits, y compris la suppression et la gestion des modules.
- `ROLE_ADMIN` gère toutes les entrées du coffre. La gestion des utilisateurs nécessite l'attribution du module `users`.
- `ROLE_USER` doit recevoir le module `passwords` et un partage explicite pour voir une entrée.
- Le droit de modification rapide ne permet de changer que la valeur chiffrée du mot de passe.

Les contrôles sont appliqués par les voters et services. Masquer un bouton Twig n'est jamais le seul contrôle.

## Chiffrement

Les secrets sont chiffrés avec `sodium_crypto_secretbox`. La clé `APP_VAULT_KEY` ne doit jamais être modifiée sans procédure de rechiffrement, faute de quoi les données existantes deviendraient illisibles. Elle doit être sauvegardée séparément de la base de données.

Les mots de passe ne sont pas intégrés au HTML des listes. Ils sont déchiffrés uniquement par un endpoint autorisé et protégé par CSRF au moment de la copie.

## Intégration

- Les nouvelles entités utilisent `TimestampableUserTrait`.
- `EntityAuditSubscriber` renseigne automatiquement les dates et utilisateurs de création/modification.
- Les modules actifs attribués à l'utilisateur alimentent la sidebar via `AppExtension`.
- Les actions AJAX renvoient toujours `success`, `message` et `data`.
- Le style repose sur Bootstrap 5.3 et Bootstrap Icons ; le JavaScript utilise uniquement Fetch API.

## Vérifications utiles

```bash
php bin/console lint:container
php bin/console lint:twig templates
php bin/console doctrine:schema:validate
php bin/phpunit
```
