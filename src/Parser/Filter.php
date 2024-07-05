<?php

namespace ArieTimmerman\Laravel\SCIMServer\Parser;

use Tmilos\ScimFilterParser\Ast\Filter as AstFilter;

class Filter {
    
    public $filter;

    public function __construct(AstFilter $filter){
        $this->filter = $filter;
    }

}
