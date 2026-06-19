## 0.2.24.567 Regression Test — Event Plan deposit compensation foundation

🚨 Codex testing recommended before building agreement/proposal packets on top of this, because the Event Plan edit screen, save path, package apply path, and Locked Pay hash/snapshot behavior should be verified inside WordPress.

1. Install/update the plugin and confirm WordPress shows version `0.2.24.567`.
2. Confirm `vms/vms-build.txt` reads `0.2.24.567`.
3. Open or create an Event Plan with a primary vendor and normal Draft Pay values.
4. In Compensation → Deposit / Advance, enter a deposit amount, set status to `Unpaid`, treatment to `Creditable toward final pay`, add due/paid dates as applicable, and add a short deposit note.
5. Save the Event Plan and confirm all deposit fields persist after reload.
6. Click `Lock Draft Pay for This Event` and confirm the Locked Pay Snapshot summary includes the deposit details.
7. Change only a deposit field and save; confirm the Draft Pay differs from Locked Snapshot warning appears because the compensation hash changed.
8. Apply or re-apply a comp package and confirm existing event-level deposit fields are preserved rather than wiped.
9. Clear all deposit fields, set status to `Not required`, save, and confirm the deposit summary disappears and no stale deposit details remain in the Locked Pay summary after re-locking.
