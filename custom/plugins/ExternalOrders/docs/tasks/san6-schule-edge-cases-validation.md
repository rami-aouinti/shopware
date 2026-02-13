# SAN6 Schule – Validation des cas limites import/export

## Cas couverts
- Variantes `Gr` testées: `01`, `1`, vide.
- Références article testées: `ART.01` et `ART      01`.
- Pièces jointes testées: payload texte court et payload volumineux.
- Caractères spéciaux: accents, apostrophes et `&`.

## Retours TopM et persistance plugin
- Réponse XML TopM simulée validée: `response_code=0`, `response_message=OK Schule Edge Cases`.
- Vérification de persistance validée dans `external_order_export`:
  - `request_xml` stocké intégralement,
  - `response_xml` stocké,
  - `response_code` mappé en entier,
  - `response_message` mappé en texte.

## Écarts observés
- Aucun écart bloquant détecté sur ces cas.
- Mapping actuel confirmé:
  - `ART.01` et `ART      01` ⇒ `Referenz=ART`, `Gr=01`.
  - `Gr=1` ⇒ normalisé en `01`.
  - `Gr` vide ⇒ fallback `00`.
  - Caractères spéciaux conservés correctement dans l'XML via échappement natif XML.
  - Pièce jointe volumineuse acceptée et encodée en base64.

## Action corrective
- Pas de correction de mapping nécessaire après validation de ces scénarios.
- Des tests de non-régression dédiés ont été ajoutés pour figer ce comportement.
