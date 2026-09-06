# VMS Data Tools 0.5.55 Build Notes

- Adds contract-version-1 registration with Backstage Venue Manager's event ticket-sales provider registry.
- Keeps reporting implementation ownership inside the active Data Tools plugin and loads its reporting dependencies lazily only when BVM invokes the registered provider.
- Exposes the existing Event Command Center model and vendor-portal website/Square rollup through normalized results with provider/version provenance.
- Supports either plugin load order and rejects duplicate registration.
- Does not change Data Tools persistence, scheduling, REST, import, Square synchronization, or activation behavior.
