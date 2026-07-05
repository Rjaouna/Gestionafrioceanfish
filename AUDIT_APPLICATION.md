# Audit application Symfony Afriocean

Date : 05/07/2026

## Résumé global

L'application est globalement solide et déjà bien structurée autour de modules métier clairs : réceptions poisson, coût de revient, composition usine, intérimaires, stocks consommables, inventaire, dépenses, documents, contacts, maintenance, mots de passe et administration.

L'audit a couvert :

- 37 contrôleurs Symfony.
- 43 formulaires.
- 46 entités.
- 168 templates Twig.
- Les services métier, repositories, routes, sécurité, Ajax, modals, confirmations, validations, tableaux et éléments Bootstrap principaux.

Les corrections appliquées sont volontairement limitées aux textes visibles, messages utilisateur, libellés de formulaires, exports et retours Ajax. Les règles métier, permissions, routes publiques, noms de champs Doctrine et workflows existants ont été conservés.

## Corrections effectuées

- Correction d'un grand volume de textes français sans accents dans les modules réception, coût de revient, usine, intérimaires et stock consommable.
- Harmonisation des libellés de formulaires : quantités, températures, réception, congélation, expédition, catégories, références, téléphone, création, suppression, validation.
- Correction des messages flash et messages d'erreur métier : quantités incohérentes, capacité usine, réception verrouillée, stock, coût de revient, inventaire.
- Correction des libellés dans les exports Excel et écrans terrain liés aux réceptions.
- Correction de textes dans les boutons et confirmations : valider, clôturer, expédier, supprimer, télécharger, créer.
- Correction de plusieurs libellés de la page détaillée de réception pour rendre la lecture plus professionnelle.
- Vérification et correction des effets secondaires possibles sur les identifiants techniques : les slugs, routes, getters, setters, propriétés Doctrine et clés de workflow restent en ASCII.

## Points forts observés

- Sécurité bien présente : la majorité des contrôleurs sensibles utilisent `#[IsGranted]`, `denyAccessUnlessGranted`, des voters métier et des tokens CSRF.
- Les suppressions et actions sensibles passent largement par confirmations et tokens.
- Le système Ajax est cohérent : `JsonResponder`, `AjaxExceptionSubscriber`, `data-remote-modal`, `data-confirm-url` et rafraîchissement de zones.
- Les workflows métier récents sont bien isolés dans des services : réception poisson, usine, coût de revient, inventaire, dépenses.
- Les formulaires utilisent les types Symfony et les contraintes de validation au lieu de logique bricolée dans Twig.
- Le style Bootstrap est globalement cohérent et réutilise les composants déjà en place.

## Points d'attention

- `FishReceptionController` reste très chargé avec 849 lignes. Il fonctionne, mais il gagnerait à être découpé progressivement en contrôleurs par étape ou en handlers dédiés.
- `InventoryItemController`, `InterimWorkerController`, `AppointmentController`, `ConsumableStockController` et `CoutRevientController` sont aussi volumineux. Ce n'est pas bloquant, mais cela augmente le coût de maintenance.
- Certains templates sont très longs : `dashboard/index.html.twig`, `cout_revient/_form.html.twig`, `layout/_sidebar_links.html.twig`, `fish_reception/show.html.twig`, `cout_revient/estimation.html.twig`.
- `assets/app.js` centralise beaucoup de comportements Ajax et UI. Il fonctionne, mais un découpage futur en contrôleurs plus spécialisés rendrait les évolutions plus sûres.
- Les dashboards et pages de synthèse méritent une surveillance de performance en production si le volume de données augmente.
- Le projet contient beaucoup de règles métier récentes dans les réceptions et le coût de revient : une QA manuelle reste indispensable malgré les tests automatisés.

## Sécurité

Constats :

- Les modules sensibles sont protégés par rôles et voters : utilisateurs, corbeille, documents, dépenses, mots de passe, inventaire, maintenance, réception, coût de revient.
- Les documents/fichiers passent par des contrôleurs dédiés et des droits de consultation/téléchargement.
- Les suppressions définitives ou sensibles utilisent des tokens CSRF.
- Les routes Ajax vérifient majoritairement les droits côté contrôleur avant d'exécuter l'action.

Risque résiduel :

- Continuer à vérifier toute nouvelle route Ajax avec trois réflexes : droit d'accès, CSRF si mutation, réponse JSON claire.
- La corbeille et les suppressions définitives doivent rester réservées aux rôles élevés.

## Ajax et UX

Constats :

- Les modals distantes, confirmations, loaders et retours JSON sont déjà bien présents.
- Les pages réception et coût de revient ont une UX avancée avec calculs instantanés, badges, états et rafraîchissements de zones.
- Les tableaux sont globalement dans des conteneurs responsives.

Améliorations recommandées :

- Découper progressivement `assets/app.js` par domaine : réception, coût de revient, inventaire, documents, agenda.
- Ajouter un état de chargement homogène sur toutes les actions longues.
- Garder les boutons dangereux en `btn-outline-danger` ou `btn-danger` avec confirmation systématique.

## Architecture et maintenabilité

Recommandations prioritaires :

1. Extraire les actions du workflow réception dans des handlers ou contrôleurs dédiés par étape.
2. Transformer les gros blocs Twig en includes plus courts : résumé, workflow, actions, coût, traçabilité.
3. Ajouter quelques tests fonctionnels sur les routes critiques de réception : validation, traitement, annulation, congélation, stockage, expédition.
4. Ajouter des tests sur les règles de capacité usine et les coûts de réception.
5. Garder les libellés accentués uniquement dans les textes visibles, jamais dans les noms techniques.

## Fichiers modifiés

Principaux groupes modifiés :

- `assets/app.js`
- `src/Command/InjectJulyFishReceptionsCommand.php`
- `src/Controller/*`
- `src/Controller/Inventory/ConsumableStockController.php`
- `src/Entity/CoutRevient.php`
- `src/Entity/FishReception.php`
- `src/Entity/InterimWorker.php`
- `src/Form/*`
- `src/Repository/CoutRevientRepository.php`
- `src/Repository/InterimWorkerRepository.php`
- `src/Service/CoutRevient/*`
- `src/Service/FishReception/*`
- `src/Service/FactoryUnitService.php`
- `src/Service/Inventory/ConsumableStockService.php`
- `templates/cout_revient/*`
- `templates/factory_unit/index.html.twig`
- `templates/fish_reception/*`
- `templates/interim_worker/*`
- `templates/inventory/consumable_stock/*`
- `templates/layout/_sidebar_links.html.twig`

## Validations lancées

Résultats :

- `composer validate --no-check-publish` : OK.
- Lint PHP complet sur `src` : OK.
- `php bin/console lint:twig templates` : OK, 168 templates valides.
- `php bin/console lint:container` : OK.
- `php bin/console debug:router` : OK.
- `php -d memory_limit=-1 bin/console cache:clear` : OK.
- `node --check assets/app.js` : OK.
- `php bin/console asset-map:compile` : OK, 12 assets compilés.
- `vendor/bin/phpunit` : OK, 46 tests, 165 assertions.
- `git diff --check` : OK. Les avertissements restants concernent uniquement la conversion LF/CRLF annoncée par Git sous Windows.

Note : `cache:clear` sans limite mémoire a été utilisé car le warmup Twig/WebProfiler dépassait la limite locale PHP de 128 Mo en environnement dev. Avec `memory_limit=-1`, le cache est bien reconstruit.

## Tests manuels à effectuer

- Connexion / déconnexion.
- Dashboard principal.
- Menu latéral et recherche menu.
- Réceptions : liste, détail, nouvelle réception, modification, validation.
- Réceptions : traitement, annulation traitement, conditionnement, congélation, stockage, expédition.
- Réceptions : téléchargement/import Excel terrain.
- Composition usine : création, modification, capacité, suppression d'une pièce vide.
- Coût de revient : création, calcul instantané, charges, estimation par plage de dates, export Excel.
- Stock consommables : création article, entrée, sortie, inventaire, suppression article/mouvement.
- Intérimaires : création, statut, fin de mission, ne plus rappeler, impression.
- Documents : upload, consultation, téléchargement, partage, e-mail.
- Dépenses : création, soumission, validation, refus, paiement.
- Maintenance : intervenants, interventions, contrats, affectations.
- Corbeille : restauration et suppression définitive avec un compte habilité.

## Conclusion

L'application est exploitable et cohérente. Les corrections effectuées améliorent nettement la qualité perçue côté utilisateur sans modifier le comportement métier. Les prochains gains importants seront surtout structurels : alléger les gros contrôleurs, découper les gros templates et isoler davantage le JavaScript par module.
