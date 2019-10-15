<?php

namespace Application\Entity\Base;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

class Verifier {

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     */
    protected $id;

    /** @ORM\Column(type="string", length=255) */
    protected $user_id;

    /**
     * @ORM\OneToMany(targetEntity="VerificationRequest", mappedBy="verifier")
     */
    protected $verification_requests;

    public function __construct() {
        $this->verification_requests = new ArrayCollection();
        return $this;
    }

    public function getId() {
        return $this->id;
    }

    public function setId($id) {
        $this->id = $id;
    }
    
    public function getUserId() {
        return $this->user_id;
    }
    
    public function setUserId($uid) {
        $this->user_id = $uid;
    }

    public function getVerificationRequests() {
        return $this->verification_requests;
    }

}
