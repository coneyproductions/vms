# Show Risk Advisor Build Notes

Canonical source build spec:

- `plugins/vms-doc-archive/vms-build-specs/VMSX Weather Risk — Build Spec/vmsx-weather-risk-build-spec.md`

Implementation notes for this add-on build:

- Product name: `Show Risk Advisor`
- Plugin slug: `vmsx-weather-risk`
- Module slug: `weather_risk`
- V1 keeps the add-on advisory only and operator-facing.
- Event Plan integration stays compact and lives in the Advanced Controls area through a narrow VMS core hook.
- Weather providers are isolated behind adapters so provider payloads do not leak into UI or scoring logic.
- DT enrichment is optional and fully defensive.
- Snapshot storage is per Event Plan and reconstructible from weather + ticket inputs.
