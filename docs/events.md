# NVL seo events

This document describes the implemented source behavior. Acceptance is exercised by the owning package suites and Core committed-event regression tests; current release execution evidence is tracked in consumer-readiness.md. The authoritative machine-readable schema is [event-catalog.json](../resources/event-catalog.json), catalog version `1`. Event `schemaVersion` is independent of catalog version.

## Publication and listener timing

The native host dispatcher receives the captured event after the supplied source connection outer commit, or immediately when that connection has no active transaction.

Callbacks attach to the matching native connection and current nesting record; native outer/savepoint rollback discards the corresponding callbacks.

Missing source transaction records fail before commit; Mail Notifications reports and drops unusable observations.

Host after-commit listeners and queue after_commit policies can add their own deferral after publication. Host transaction infrastructure and dispatcher bindings are preserved.

Local callbacks are not an outbox. Process exit between commit and callback can lose delivery; no crash durability or exactly-once delivery is promised.

One canonical object is dispatched per qualifying producer call. This is local publication, not cross-process deduplication or a guarantee that repeated observations are unique.

Use Nvl\Support\Events\DomainEventDispatcher::dispatch($event, $writerConnection). Native Event::dispatch() is immediate and has no package interception.

## Payload security and no-op behavior

Package producers publish the profile ID, scope, operation, stored owner morph type and key, and localized path snapshots. Each snapshot maps a locale to its translation UUID and nullable path. The profile connection supplies commit timing.

Existing sync/archive/delete branches govern publication. No generic request deduplication is added.

Actor/owner identifiers do not grant access. Listeners must preserve the captured ownership and apply their own authorization when reading storage. Readonly payload fields and native value objects are schema facts; public constructors with mixed arrays do not create a new recursive sanitization boundary. Package producer shapes are documented below; hosts must not attach models, mutable service objects or private arbitrary data.

## Canonical events

| Event | Schema version | Trigger |
| --- | --- | --- |
| [SeoProfileChanged](#seoprofilechanged) | 2 | SEO profile state changed. |

### SeoProfileChanged

`Nvl\Seo\Events\SeoProfileChanged` · [source](../src/Events/SeoProfileChanged.php) · event schema `2` for package-produced events.

Create has an empty previous snapshot, and delete has an empty current snapshot. Sync captures the complete set on both sides, including locales removed by a replace. Archive and restore carry the same path snapshots on both sides. The event uses the stored owner morph identity. Existing manual construction defaults to schema `1`.

Constructor parameters, in native order:

| Parameter | Native PHP type | Visibility | Default | Collection shape |
| --- | --- | --- | --- | --- |
| `$profileId` | `string` | public | `required` | — |
| `$scope` | `string` | public | `required` | — |
| `$operation` | `string` | public | `required` | — |
| `$schemaVersion` | `int` | public | `1` | — |
| `$ownerType` | `?string` | public | `null` | — |
| `$ownerId` | `?string` | public | `null` | — |
| `$previousTranslations` | `array` | public | `[]` | `array<string, array{id:string,path:?string}>` |
| `$currentTranslations` | `array` | public | `[]` | `array<string, array{id:string,path:?string}>` |

Public payload fields:

| Field | Native PHP type | Collection shape |
| --- | --- | --- |
| `$profileId` | `string` | — |
| `$scope` | `string` | — |
| `$operation` | `string` | — |
| `$schemaVersion` | `int` | — |
| `$ownerType` | `?string` | — |
| `$ownerId` | `?string` | — |
| `$previousTranslations` | `array` | Locale keyed path snapshot |
| `$currentTranslations` | `array` | Locale keyed path snapshot |

Producers and exact scheduling connection expressions (variables are resolved in the linked source):

| Producer | Source connection |
| --- | --- |
| [Services/SeoProfiles.php](../src/Services/SeoProfiles.php) (`publishChange()` from `archive()`, `delete()` and `sync()`) | `$connection`, resolved from `$profile->getConnection()` |

## Deferred acceptance checks

Final testing must compare catalog types/defaults/aliases with actual classes, recursively inspect producer payloads, and prove source outer commit, nested rollback, unrelated connection independence and retry behavior without an uncommitted test-harness transaction. Where applicable it must cover cached/queued listeners, canonical fakes, ownership capture, package no-op guards and observational failure containment. This document does not report those checks as passing.

## Consumer event assertions

Use the canonical event class for `Event::fake([...])` and `Event::assertDispatched(...)`. Laravel fake filters compare the emitted class name. Update listeners and fakes to the current source event class; old aliases are not a Light compatibility API.

