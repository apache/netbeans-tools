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
 *   https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

namespace Application\Entity\Base;

use Doctrine\ORM\Mapping as ORM;

class VerificationRequest {

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     */
    protected $id;

    /** @ORM\Column(type="datetime") */
    protected $created_at;

    /** @ORM\Column(type="integer") */
    protected $vote;

    /** @ORM\Column(type="datetime") */
    protected $voted_at;

    /** @ORM\Column(type="text") */
    protected $comment;

    /** @ORM\Column(type="integer") */
    protected $verification_id;

    /** @ORM\Column(type="integer") */
    protected $verifier_id;
    
    /**
     * @ORM\ManyToOne(targetEntity="Verification", inversedBy="verification_requests")
     * @ORM\JoinColumn(name="verification_id", referencedColumnName="id")
     */
    protected $verification;
    
    /**
     * @ORM\ManyToOne(targetEntity="User", inversedBy="verification_requests")
     * @ORM\JoinColumn(name="verifier_id", referencedColumnName="id")
     */
    protected $verifier;

    public function __construct() {
        return $this;
    }

    public function getId() {
        return $this->id;
    }

    public function setId($id) {
        $this->id = $id;
    }

    public function getCreatedAt() {
        return $this->created_at;
    }

    public function setCreatedAt($cat) {
        $this->created_at = $cat;
    }

    public function getVote() {
        return $this->vote;
    }

    public function setVote($vote) {
        $this->vote = $vote;
    }

    public function getVotedAt() {
        return $this->voted_at;
    }

    public function setVotedAt($vat) {
        $this->voted_at = $vat;
    }

    public function getComment() {
        return $this->comment;
    }

    public function setComment($cmnt) {
        $this->comment = $cmnt;
    }

    public function getVerificationId() {
        return $this->verification_id;
    }

    public function setVerificationId($vid) {
        $this->verification_id = $vid;
    }

    public function getVerifierId() {
        return $this->verifier_id;
    }

    public function setVerifierId($vid) {
        $this->verifier_id = $vid;
    }
    
    public function getVerification() {
        return $this->verification;
    }

    public function setVerification($v) {
        $this->verification = $v;
    }
    
    public function getVerifier() {
        return $this->verifier;
    }

    public function setVerifier($v) {
        $this->verifier = $v;
    }
}
