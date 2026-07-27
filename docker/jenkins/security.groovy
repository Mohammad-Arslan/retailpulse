// =============================================================================
// RetailPulse — Jenkins security bootstrap
//
// Idempotent: runs on every boot (does NOT self-delete, unlike
// bootstrap-credentials.groovy) so the security configuration can't drift.
// Re-applying the same realm/authorization/CSRF settings every start is safe.
//
// Skips itself entirely — leaving Jenkins on the normal manual setup wizard
// (itself secure-by-default: no anonymous access until the wizard is
// completed with the file-based initialAdminPassword) — if JENKINS_ADMIN_USER
// / JENKINS_ADMIN_PASSWORD are not set. There is intentionally no baked-in
// default Jenkins admin password.
// =============================================================================
import jenkins.model.Jenkins
import hudson.security.HudsonPrivateSecurityRealm
import hudson.security.GlobalMatrixAuthorizationStrategy
import hudson.security.csrf.DefaultCrumbIssuer
import jenkins.install.InstallState

def jenkins = Jenkins.get()

def adminUser = System.getenv('JENKINS_ADMIN_USER')
def adminPassword = System.getenv('JENKINS_ADMIN_PASSWORD')

if (!adminUser?.trim() || !adminPassword?.trim()) {
    println '[security] JENKINS_ADMIN_USER / JENKINS_ADMIN_PASSWORD not set -- skipping automated security setup.'
    println '[security] Jenkins will show the normal setup wizard (Manage Jenkins -> Security) on first open.'
    return
}

// HudsonPrivateSecurityRealm(false) = no self-registration ("Allow users to sign up").
def realm = new HudsonPrivateSecurityRealm(false)
realm.createAccount(adminUser, adminPassword)
jenkins.setSecurityRealm(realm)

// Only the configured admin gets ADMINISTER (implies every other permission).
// Anonymous gets nothing -- matches this stack's "127.0.0.1 + SSH tunnel only"
// access model (see docs/ops-stack.md §1/§2.1); there is no legitimate
// anonymous caller.
def strategy = new GlobalMatrixAuthorizationStrategy()
strategy.add(Jenkins.ADMINISTER, adminUser)
jenkins.setAuthorizationStrategy(strategy)

// CSRF protection (on by default in modern Jenkins; set explicitly so it's
// never silently disabled by a plugin/config change).
jenkins.setCrumbIssuer(new DefaultCrumbIssuer(true))

// Only mark install complete (skip the setup wizard) once a real security
// realm has actually been configured above -- never unconditionally, or a
// misconfigured/missing admin would boot into a wide-open Jenkins instead of
// the wizard's safe default.
jenkins.setInstallState(InstallState.INITIAL_SETUP_COMPLETED)

jenkins.save()
println "[security] Security realm + matrix authorization (admin='${adminUser}') + CSRF crumb issuer configured; setup wizard skipped."
