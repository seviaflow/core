# File lifecycle contract

SeviaFlow treats a managed file as the pair `(disk, path)`. A path is eligible for lifecycle cleanup only when `FilePathGuard` confirms that it is a relative path inside the configured `FileField` directory. URLs, absolute paths, backslashes, empty path segments and traversal segments are never removed by the lifecycle.

## Supported references

`FileLifecycleManager` checks every `FileField` passed for the current resource:

- another field on the same model instance;
- another row of the same model on the same disk;
- soft-deleted rows of that model, so a later restore can still recover its file reference.

The disk is part of identity. The same path on two disks is two different files. The query uses the model's normal query and therefore keeps tenant, authorization and other application global scopes. Only the `SoftDeletingScope` is removed for models using `SoftDeletes`, because the restore contract requires trashed references to protect their files.

References owned by a different model, a polymorphic relation, an external database or an unregistered field are not inferred. Such ownership needs an explicit resolver in a future extension; this lifecycle does not perform a cross-model media scan or garbage collection.

If the reference query fails or its ownership cannot be established, the file is preserved and a warning is written with the model, field, disk and exception class. The lifecycle never converts an uncertain result into a physical delete.

## Mutations and transactions

File values use an explicit mutation contract:

- `retain` or `null` leaves the stored value and file unchanged;
- `remove` clears the model value and removes the old file only when no supported reference remains;
- an upload replaces the model value after the new file is stored, then removes the old file after commit when it is unreferenced.

`ResourceOperationService` owns the database transaction and the file lifecycle. When it is called inside an enclosing transaction, the rollback cleanup callback is registered before the service opens its nested transaction. A rollback therefore removes newly stored files without requiring the caller to invoke a cleanup method. Save failures retain the old file and remove the new file. After commit, cleanup is idempotent and a missing file is not an error.

Supported connections must provide Laravel's `afterCommit` and `afterRollBack` transaction callbacks. If rollback callbacks are unavailable, the lifecycle preserves newly stored files and emits a diagnostic instead of deleting data speculatively; that connection is not a supported atomic file-operation profile until its transaction integration is supplied.

## Ownership boundary

The lifecycle protects references declared by the current resource's fields and its model query. It does not claim atomicity between a database and a remote object store, does not delete arbitrary storage paths, and does not provide a media library or orphan-file collector. Applications that store a file reference outside this contract must retain it through their own explicit resolver.
