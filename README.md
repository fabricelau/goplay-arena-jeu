# Mini-jeux GoPlay Arena

Jeux des QR codes de table de GoPlay Arena (goplayarena.re/jeu).

- `games/` : les pages des jeux et `manifest.json` (version + empreintes SHA-256).
- L'extension WordPress `goplay-arena-jeu` (1.3.0 et plus) lit ce dossier toutes les 15 minutes
  et installe les nouvelles versions (bouton « Mettre à jour les jeux maintenant » dans Outils › Mini-jeux GoPlay).
- `plugin/goplay-arena-jeu.php` : l'extension elle-même ; le site l'installe seul quand sa version (en-tête « Version: ») augmente. Toujours `php -l` avant d'envoyer.
- Après une modification : `python3 publish.py`, puis commit et push sur `main`.

| Jeu | Fichier | Adresse |
|---|---|---|
| Arena Defender | game.html | /jeu/ |
| Arena Pilot | pilot.html | /jeu/pilot/ |
| Pixel Rush | pixel.html | /jeu/pixel/ |
| Laser Reflex | laser.html | /jeu/laser/ |
| Target Chain | chain.html | /jeu/chain/ |
| Memory Grid | memory.html | /jeu/memory/ |
| Color Switch | color.html | /jeu/color/ |
