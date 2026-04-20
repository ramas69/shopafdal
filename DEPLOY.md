# Déploiement Afdal sur o2switch

URL prod : **https://afdal.sora3439.odns.fr**

## 0 — Prérequis côté o2switch (cPanel)

1. **PHP 8.2+** activé pour le compte/domaine (section "Sélecteur de version de PHP").
   - Extensions requises : `pdo_mysql`, `intl`, `mbstring`, `opcache`, `ctype`, `iconv`, `gd`.
2. **Base MySQL/MariaDB** créée via cPanel → "Bases de données MySQL" :
   - Nom : `sora3439_afdal` (ou similaire, préfixé par le user cPanel)
   - User dédié avec mot de passe fort, ajouté à la DB avec tous les privilèges
   - Host : `localhost`, port `3306`
   - Note la version affichée (ex. MariaDB 10.11, MySQL 8.0) pour `serverVersion`
3. **SSH activé** sur le compte o2switch (pour composer + migrations). Si pas dispo, utiliser le Terminal cPanel.
4. **Composer** disponible côté serveur (o2switch l'a par défaut via `composer` ou `php ~/composer.phar`).
5. **Compte email** `afdal@sora3439.odns.fr` créé (déjà fait).

## 1 — Préparation locale (avant upload)

```bash
# Depuis le projet sur ta machine
composer install --no-dev --optimize-autoloader
php bin/console asset-map:compile --env=prod
php bin/console cache:clear --env=prod
```

> `asset-map:compile` génère `public/assets/*` (ignored par git) avec les fichiers hashés.

## 2 — Upload des fichiers

**Ce qui va sur le serveur** (FTP, SFTP ou git clone) :
- Tout le projet **sauf** `.env.local`, `var/`, `lineone-html/`, `.git/`, `tests/`, `phpunit.xml`
- Inclure `vendor/` si tu ne peux pas lancer composer sur le serveur (sinon `composer install` côté serveur)
- Inclure `public/assets/` (asset-map:compile)

**Structure attendue sur o2switch** (exemple) :
```
~/afdal/                  ← racine du projet (hors public_html)
  bin/
  config/
  migrations/
  public/                 ← web root à pointer
  src/
  templates/
  translations/
  var/                    ← doit être writable par PHP
  vendor/
  .env
  .env.prod
  .env.prod.local         ← À CRÉER SUR LE SERVEUR (voir étape 3)
  composer.json
```

Puis dans cPanel, configurer le **Document Root** du sous-domaine `afdal.sora3439.odns.fr` vers `~/afdal/public/` (pas `public_html`).

> Alternative si tu ne peux pas changer le document root : créer un lien symbolique `ln -s ~/afdal/public ~/public_html/afdal` et ajouter un `.htaccess` au-dessus qui rewrite vers ce lien.

## 3 — Configuration secrets (sur le serveur)

Créer `~/afdal/.env.prod.local` (NE PAS commiter) :

```bash
# Sur le serveur
cd ~/afdal
cat > .env.prod.local << 'EOF'
APP_SECRET=REMPLACE_PAR_32_CARACTERES_HEX_ALEATOIRES

DATABASE_URL="mysql://AFDAL_DB_USER:AFDAL_DB_PASSWORD@127.0.0.1:3306/sora3439_afdal?serverVersion=10.11-MariaDB&charset=utf8mb4"

# SMTP o2switch — host réel à récupérer dans cPanel → Webmail → "Configurer un client mail" (ex : tabebuia.o2switch.net, port 465 SSL)
MAILER_DSN="smtp://afdal%40sora3439.odns.fr:LE_MOT_DE_PASSE_EMAIL@tabebuia.o2switch.net:465?encryption=ssl"
EOF
chmod 600 .env.prod.local
```

> **Note encodage URL** : dans `MAILER_DSN`, le `@` du username DOIT être encodé en `%40` (ex. `afdal%40sora3439.odns.fr`). Idem si le password contient des caractères spéciaux (`@` → `%40`, `#` → `%23`, etc.).

Générer `APP_SECRET` :
```bash
php -r 'echo bin2hex(random_bytes(16)) . "\n";'
```

## 4 — Install dépendances + compilation assets (si fait côté serveur)

Si tu n'as pas uploadé `vendor/` et `public/assets/` :

```bash
cd ~/afdal
composer install --no-dev --optimize-autoloader --no-interaction
php bin/console asset-map:compile --env=prod --no-debug
```

## 5 — Migrations Doctrine

```bash
cd ~/afdal
php bin/console doctrine:migrations:migrate --env=prod --no-interaction
```

> Premier déploiement : la migration MySQL unique `Version20260420195805` crée tout le schéma.

## 6 — Permissions + cache

```bash
cd ~/afdal
chmod -R 775 var/
php bin/console cache:clear --env=prod --no-debug
php bin/console cache:warmup --env=prod --no-debug
```

Sur o2switch, l'utilisateur PHP (via PHP-FPM) doit pouvoir écrire dans `var/`. Si des erreurs de cache apparaissent, passer en `777` temporairement pour diagnostiquer, puis revenir à `775`.

## 7 — Création du premier admin

Depuis le shell serveur :

```bash
cd ~/afdal
php bin/console app:create-admin ton-email@exemple.fr "Ton Nom" 'ton-mot-de-passe' --env=prod
```

Arguments : email, nom complet entre guillemets, mot de passe entre apostrophes (si caractères spéciaux).

## 8 — Vérification

Ouvrir **https://afdal.sora3439.odns.fr** :
- Page d'accueil s'affiche
- `/login` fonctionne et n'affiche pas d'erreur 500
- Connecter l'admin, vérifier `/admin` (dashboard)
- Tester envoi email (ex. inviter un membre — doit arriver sur un vrai email)

## 9 — SSL (HTTPS)

Via cPanel → "SSL/TLS" → activer AutoSSL pour `afdal.sora3439.odns.fr`. Une fois le certificat installé, décommenter dans `public/.htaccess` le bloc "Force HTTPS" (lignes 60-63) pour forcer la redirection.

## 10 — Mises à jour ultérieures

À chaque update :

```bash
ssh o2switch
cd ~/afdal
git pull                                      # ou upload via SFTP
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console asset-map:compile --env=prod
php bin/console cache:clear --env=prod
```

## Troubleshooting

| Problème | Cause | Fix |
|---|---|---|
| **500 sur toutes les pages** | `.htaccess` manquant ou `var/` non writable | `chmod -R 775 var/` + vérifier `public/.htaccess` uploadé |
| **DATABASE_URL not set** | `.env.prod.local` absent ou mauvais chemin | Vérifier présence du fichier à la racine projet (pas dans `public/`) |
| **Emails ne partent pas** | MAILER_DSN mal encodé | Encoder `@` en `%40` dans username |
| **Assets 404 (CSS/JS)** | `asset-map:compile` pas lancé | Relancer `php bin/console asset-map:compile --env=prod` |
| **Session lost / CSRF fail** | `var/sessions/` pas writable | `chmod -R 775 var/sessions` |
| **Migration fail "permission denied"** | User DB sans droits DDL | Via cPanel, donner `ALL PRIVILEGES` au user sur la base |

## Fichiers à ne JAMAIS uploader

- `.env.local` (contient creds dev)
- `.env.test.local`
- `var/` (sera créé côté serveur)
- `.git/`, `tests/`, `phpunit.xml`, `lineone-html/`
- `node_modules/` (si jamais présent)
