## 0.2.24.578 Test Plan — staffing template replace + expected attendance

### Install / load
1. Upload/activate 0.2.24.578.
2. Open an Event Plan with staffing enabled.

### Template replace semantics
1. Create or edit a staffing template that omits at least one role previously used on an event.
2. On an Event Plan that currently has that omitted role active, choose the template and **Replace staffing from template**.
3. Save/apply.
4. Confirm:
   - included roles are seeded correctly
   - omitted roles no longer show as required
   - omitted roles render with **Staff needed = 0** / not in use unless intentionally added back
5. Save the Event Plan again and confirm the omitted roles remain inactive.

### Blank event default behavior
1. Open a brand-new / effectively blank Event Plan with no event-level staffing data and no applied template.
2. Confirm Staff Role defaults still appear as before for first-time setup.

### Expected attendance sourcing
1. Use an Event Plan with sold tickets (`qty_sold > 0`).
2. Set `_vms_true_headcount` to `0` (or use an event where it is already zero).
3. Reload the Event Plan.
4. Confirm the staffing summary no longer shows `0 (True headcount)` and instead reflects the sold-ticket attendance source.
5. If admissions entries exist, confirm the attendance source becomes ticket sales + admissions.
6. If no ticket/admission data exists but true headcount does, confirm true headcount still appears as the fallback source.

### Regression spot checks
1. Apply template in **Merge missing roles only** and confirm existing role slots are preserved.
2. Open Staffing Templates and confirm editor still loads/saves.
3. Open Staff profile qualification cards and confirm no regressions there.
4. Verify Event Plan staffing timing controls still switch correctly by Absolute vs Relative mode.
