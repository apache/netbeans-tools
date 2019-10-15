<?php

namespace Application\Entity;

use Doctrine\ORM\Mapping as ORM;
use Zend\Mail;

/**
 * @ORM\Entity
 * @ORM\Table(name="verification_request")
 */
class VerificationRequest extends Base\VerificationRequest {

    const VOTE_GO = 1;
    const VOTE_NOGO = -1;
    const VOTE_UNDECIDED = 0;

    public function sendVerificationMail($plugin) {
        // TODO - remove when we have auth ready
        return;

        $body = 'Hello verifier,

this is to inform you about new verification request requiring your immediate attention.

Plugin: '.$plugin->getName().'
NetBeans version: '.$this->getVerification()->getNbVersionPluginVersion()->getNbVersion()->getVersion().'

Please login to the NetBeans Plugin Portal at your earliest convenience, test the plugin above in Apache NetBeans IDE it was written for and either Approve or Reject publishing the plugin on the target Plugin Portal Update Center. The testing should validate that the plugin can be installed, deactivated, activated and uninstalled smoothly. If it registers its own Update Center, the uninstallation of the plugin removes the Update Center as well. If you decide to reject a plugin, please include appropriate justification in the comments field for the plugin owner.

http://netbeans-vm.apache.org/pluginportal/verification/list

Thanks for your help!
NetBeans development team

P.S.: This is an automatic email. DO NOT REPLY to this email.';

        $mail = new Mail\Message();
        $mail->setBody($body);
        $mail->setFrom('webmaster@netbeans.apache.org', 'NetBeans webmaster');
        $mail->addTo($this->getVerifier()->getUserId());
        $mail->setSubject('Verification request for NetBeans plugin: '.$plugin->getName());
        $transport = new Mail\Transport\Sendmail();
        
        $transport->send($mail);
    }

    public function getVoteBadgeTitle() {
        if ($this->vote === self::VOTE_NOGO) {
            return 'NoGo';
        } elseif ($this->vote === self::VOTE_GO) {
            return 'Go';
        }
        return 'Undecided';
    }

    public function getVoteBadgeClass() {
        if ($this->vote === self::VOTE_NOGO) {
            return 'badge-red';
        } elseif ($this->vote === self::VOTE_GO) {
            return 'badge-green';
        }
    }
}
