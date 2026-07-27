// One-shot bootstrap: create VPS SSH credential + Pipeline job, then delete this file.
import jenkins.model.Jenkins
import com.cloudbees.plugins.credentials.CredentialsScope
import com.cloudbees.plugins.credentials.SystemCredentialsProvider
import com.cloudbees.plugins.credentials.domains.Domain
import com.cloudbees.jenkins.plugins.sshcredentials.impl.BasicSSHUserPrivateKey
import org.jenkinsci.plugins.workflow.job.WorkflowJob
import org.jenkinsci.plugins.workflow.cps.CpsFlowDefinition

def jenkins = Jenkins.get()
def domain = Domain.global()
def store = SystemCredentialsProvider.getInstance().getStore()

// --- retailpulse-vps-ssh (Contabo staging) ---
def keyPath = '/var/jenkins_home/bootstrap/retailpulse_staging_ed25519'
def keyFile = new File(keyPath)
if (!keyFile.exists()) {
    throw new IllegalStateException("Missing private key at ${keyPath}")
}
def keyText = keyFile.getText('UTF-8').trim() + '\n'
def keySource = new BasicSSHUserPrivateKey.DirectEntryPrivateKeySource(keyText)
def vpsCred = new BasicSSHUserPrivateKey(
    CredentialsScope.GLOBAL,
    'retailpulse-vps-ssh',
    'ubuntu',
    keySource,
    '',
    'RetailPulse Contabo staging (retailpulse_staging_ed25519)'
)

store.getCredentials(domain)
    .findAll { it.id == 'retailpulse-vps-ssh' }
    .each { store.removeCredentials(domain, it) }
store.addCredentials(domain, vpsCred)
println '[bootstrap] Credential retailpulse-vps-ssh created/updated'

// --- Pipeline job (script from file copied into jenkins_home) ---
def jobName = 'retailpulse'
def jf = new File('/var/jenkins_home/bootstrap/Jenkinsfile')
if (!jf.exists()) {
    throw new IllegalStateException('Missing /var/jenkins_home/bootstrap/Jenkinsfile')
}

def job = jenkins.getItem(jobName)
if (job == null) {
    job = jenkins.createProject(WorkflowJob, jobName)
    println "[bootstrap] Created job ${jobName}"
} else {
    println "[bootstrap] Updating existing job ${jobName}"
}
job.definition = new CpsFlowDefinition(jf.getText('UTF-8'), true)
job.save()

// Remove one-shot script so it does not re-run on every restart
def self = new File('/var/jenkins_home/init.groovy.d/99-retailpulse-bootstrap.groovy')
if (self.exists()) {
    self.delete()
    println '[bootstrap] Removed init.groovy.d/99-retailpulse-bootstrap.groovy'
}

println '[bootstrap] Done — open http://127.0.0.1:9080/job/retailpulse/'
