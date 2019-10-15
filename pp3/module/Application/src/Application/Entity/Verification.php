<?php

namespace Application\Entity;

use Doctrine\ORM\Mapping as ORM;
use Application\Entity\VerificationRequest;

/**
 * @ORM\Entity
 * @ORM\Table(name="verification")
 */
class Verification extends Base\Verification {

    const STATUS_REQUESTED = 0;
    const STATUS_PENDING = 2;
    const STATUS_GO = 1;
    const STATUS_NOGO = -1;

    const GO_VOTES_MIN = 2;

    public function addVerificationRequest($req) {
        $this->verification_requests[] = $req;
    }
    
    public function createRequests($verifiers, $plugin) {
        foreach($verifiers as $verifier) {
            $req = new VerificationRequest();
            $req->setCreatedAt(new \DateTime('now'));
            $req->setVerification($this);
            $req->setVerifier($verifier);
            $req->setVote(VerificationRequest::VOTE_UNDECIDED);
            $this->addVerificationRequest($req);
            $req->sendVerificationMail($plugin);
        }
    }

    public function getStatusBadgeClass() {
        if ($this->status === self::STATUS_NOGO) {
            return 'badge-red';
        } elseif ($this->status === self::STATUS_GO) {
            return 'badge-green';
        }
    }

    public function getStatusBadgeTitle() {
        if ($this->status === self::STATUS_NOGO) {
            return 'NoGo';
        } elseif ($this->status === self::STATUS_GO) {
            return 'Go';
        }
        return 'Pending';
    }

    public function resolveStatus($votesBreakdown) {
        // rule: PASSED = 0 NoGO AND >=2 Go Votes
        $noGos = $votesBreakdown[VerificationRequest::VOTE_NOGO];
        $gos = $votesBreakdown[VerificationRequest::VOTE_GO];
        $undecided = $votesBreakdown[VerificationRequest::VOTE_UNDECIDED];
        if (count($noGos)) {
            $this->status = self::STATUS_NOGO;
            return $this->status;
        } 
        if (count($gos) >= self::GO_VOTES_MIN) {
            $this->status = self::STATUS_GO;
            return $this->status;
        }
        $this->status = self::STATUS_PENDING;
        return $this->status;
    }
}
