# local_autogroup — versione CargoSchool

Fork di `emmarichardson/local_autogroup`, basato sul commit ufficiale `ec32c54`
(release 4.5.3, versione `2026091600`). Le modifiche vivono sul ramo `cargoschool`;
il ramo `master` resta identico all'originale. Ogni riga modificata è racchiusa tra i
commenti `// CARGOSCHOOL: start.` e `// CARGOSCHOOL: end.`

## Cosa cambia

Solo per gli insiemi di gruppi automatici che raggruppano per **institution**
(modulo `profile_field`, campo `institution`):

| Valore di institution | Comportamento |
|---|---|
| vuoto | nessun gruppo — **identico all'originale** |
| un valore senza `" - "`, diverso da `*` | un gruppo con quel valore grezzo — **identico all'originale** |
| più sedi separate da `" - "` | un gruppo per ogni sede, creato se non esiste (stesso idnumber che avrebbe uno studente di quella sede) |
| `*` | i gruppi di sede **già esistenti** nell'insieme, solo per le sedi dei tenant dell'utente; nessun gruppo creato |

Quando in un corso nasce un gruppo di sede, oppure un utente esce da un gruppo di sede,
gli utenti del corso con `*` vengono riallineati: entrano nel nuovo gruppo ed escono dai
gruppi di sedi che non esistono più (così i gruppi vuoti vengono eliminati come sempre).

Il raggruppamento per altri campi (department compreso) non cambia.

## Dipendenze

- Le sedi multiple funzionano anche senza `local_cargoservices` (regola equivalente interna).
- `*` richiede `local_cargoservices` 0.18.0 o successiva (`manager::get_user_tenants()`,
  `population::tenant_sites()`); senza, `*` non assegna alcun gruppo.
- Nessuna modifica al database.

## File modificati

- `classes/sort_module/profile_field.php` — lettura di elenchi e `*`; costante
  `CARGOSCHOOL_MULTISITE`, letta da `local_cargoservices` per riconoscere questa versione.
- `classes/domain/autogroup_set.php` — passaggio dell'id dell'insieme al modulo;
  riallineamento degli utenti `*`.
- `version.php` — `2026091601`, `4.5.3-cargoschool.1`, `requires` Moodle 4.5, `supported` 4.5–5.1.
- `CHANGES.md`, `CARGOSCHOOL.md`, `tests/cargoschool_multisite_test.php`.

## Da verificare negli insiemi dei corsi

- Il ruolo **Docente non editor** deve essere tra i ruoli ammessi, altrimenti i manager
  non vengono raggruppati.
- Con `preservemanual` attivo, le assegnazioni manuali dei manager restano: vanno
  ripulite in migrazione (`local_cargoservices/cli/migrate_manager_sites.php --cleanmanual`).

## Aggiornare dall'originale

```bash
git fetch upstream
git switch master && git merge --ff-only upstream/master && git push origin master
git switch cargoschool && git rebase master
git push --force-with-lease
```
Poi aggiornare `version.php` (versione ufficiale + 1, release `<ufficiale>-cargoschool.N`)
e ripetere le prove su staging.

## Nota su un difetto dell'originale (non corretto)

`sort_module::__get()` contiene `if ($attribute = 'groups')` (assegnazione al posto del
confronto). È innocuo per l'uso attuale ed è lasciato invariato per non allontanarsi
dall'originale.
