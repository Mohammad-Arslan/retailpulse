// One-shot: refresh Pipeline job script from bootstrap/Jenkinsfile, then self-delete.
import jenkins.model.Jenkins
import org.jenkinsci.plugins.workflow.job.WorkflowJob
import org.jenkinsci.plugins.workflow.cps.CpsFlowDefinition

def jenkins = Jenkins.get()
def jobName = 'retailpulse'
def jf = new File('/var/jenkins_home/bootstrap/Jenkinsfile')
if (!jf.exists()) {
    throw new IllegalStateException('Missing /var/jenkins_home/bootstrap/Jenkinsfile')
}
def job = jenkins.getItem(jobName)
if (job == null) {
    job = jenkins.createProject(WorkflowJob, jobName)
}
job.definition = new CpsFlowDefinition(jf.getText('UTF-8'), true)
job.save()
println "[bootstrap] Job ${jobName} pipeline script refreshed"

def self = new File('/var/jenkins_home/init.groovy.d/99-refresh-retailpulse-job.groovy')
if (self.exists()) {
    self.delete()
}
println '[bootstrap] Done'
