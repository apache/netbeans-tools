<?php
/**
 * Licensed to the Apache Software Foundation (ASF) under one
 * or more contributor license agreements.  See the NOTICE file
 * distributed with this work for additional information
 * regarding copyright ownership.  The ASF licenses this file
 * to you under the Apache License, Version 2.0 (the
 * "License"); you may not use this file except in compliance
 * with the License.  You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

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

    /**
     * @param User[] $verifiers
     * @param Plugin $plugin
     */
    public function createRequests($verifiers, $plugin) {
        foreach($verifiers as $verifier) {
            $req = new VerificationRequest();
            $req->setCreatedAt(new \DateTime('now'));
            $req->setVerification($this);
            $req->setVerifier($verifier);
            $req->setVote(VerificationRequest::VOTE_UNDECIDED);
            $this->addVerificationRequest($req);
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
