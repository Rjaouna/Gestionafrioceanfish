# Module de gestion des documents

## Configuration

Les fichiers sont stockés dans un dossier privé, non exposé par le serveur web :

```dotenv
DOCUMENT_STORAGE_DIRECTORY=var/storage/documents
DOCUMENT_MAX_FILE_SIZE=10485760
```

`DOCUMENT_STORAGE_DIRECTORY` peut être relatif au projet ou absolu. Le dossier `var/` est ignoré par Git.

Pour activer les notifications de partage et l’envoi de documents par e-mail, remplacer le transport nul par un SMTP réel :

```dotenv
MAILER_DSN=smtp://user:password@smtp.example.com:587
```

## Sécurité

- Les fichiers ne sont jamais servis depuis `public/`.
- Les téléchargements passent par `DocumentController::download()`.
- L’envoi d’un document par e-mail est autorisé uniquement aux utilisateurs qui peuvent le télécharger ; le fichier est ajouté en pièce jointe.
- Les droits sont vérifiés par `DocumentVoter` et les services métier.
- Les extensions exécutables sont refusées, même si le stockage est privé.
- Les partages peuvent expirer et sont limités aux utilisateurs actifs.
