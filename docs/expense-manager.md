# Module Dépenses

Le module `Dépenses` permet de suivre les sorties d'argent de l'entreprise avec justificatif privé, workflow de validation et statistiques simples.

## Installation

La migration du module a été ajoutée et appliquée localement :

```bash
php bin/console doctrine:migrations:migrate
```

Variables disponibles dans `.env` :

```dotenv
EXPENSE_STORAGE_DIRECTORY=var/storage/expenses
EXPENSE_MAX_FILE_SIZE=10485760
```

Les justificatifs sont stockés dans `var/storage/expenses` et ne sont jamais exposés publiquement. Le téléchargement passe par une route sécurisée.

## Utilisation

1. Activer le module `Dépenses` dans la gestion des modules si nécessaire.
2. Attribuer l'accès au module aux utilisateurs concernés.
3. Aller dans `Dépenses > Nouvelle dépense`.
4. Renseigner le montant HT et le taux de TVA : le TTC est calculé automatiquement.
5. Ajouter un justificatif si besoin.
6. Soumettre la dépense pour validation.

## Workflow

- Un utilisateur peut créer et voir ses propres dépenses.
- Un administrateur peut valider, refuser, marquer comme payée et archiver.
- Un super administrateur peut tout faire, y compris supprimer.
- Une dépense payée n'est plus modifiable sauf par super administrateur.
- Une dépense refusée demande un motif.

## Catégories

Les catégories par défaut sont créées par migration : carburant, péage, fournitures, matériel, abonnements, loyer, prestataire, salaire, frais bancaires, assurance, entretien véhicule et autre.

Les admins peuvent les gérer depuis `Dépenses > Catégories`.
