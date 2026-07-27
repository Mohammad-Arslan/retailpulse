# Jenkins bootstrap directory

Bind-mounted read-only into `retailpulse-jenkins` at `/var/jenkins_home/bootstrap`
(see `docker-compose.ops.yml`). `docker/jenkins/bootstrap-credentials.groovy` reads
from here on first boot to create the `retailpulse-vps-ssh` credential and the
`retailpulse` Pipeline job, then self-deletes (from the Jenkins volume, not this
directory).

Nothing in this directory is committed except this file and `.gitignore` —
both of the following are gitignored on purpose:

| File | How it gets here |
| :--- | :--- |
| `Jenkinsfile` | Auto-copied from `jenkins/Jenkinsfile` by `scripts/ops-up.sh` on every run — never hand-edit the copy here, edit the real file. |
| `retailpulse_staging_ed25519` | **You** place it here manually — the private key matching the `retailpulse deploy key` / SSH access already trusted on the VPS for the `ubuntu` user (see `DEPLOYMENT-CREDENTIALS.md`, which is itself gitignored). Never commit a private key. |

If the key isn't here yet, `bootstrap-credentials.groovy` logs a boot-time
error and skips credential creation — Jenkins still starts fine. Add the key
and restart the container (`docker compose -p retailpulse-ops -f
docker-compose.ops.yml restart jenkins`) once it's in place.
