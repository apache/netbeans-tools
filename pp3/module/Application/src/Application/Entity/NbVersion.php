<?php

namespace Application\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="nb_version")
 */
class NbVersion extends Base\NbVersion {
        
    const VERIFIABLE_YES = 1;
    const VERIFIABLE_NO  = 0;

}
