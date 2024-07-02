<?php

namespace ArieTimmerman\Laravel\SCIMServer\Parser;

use Tmilos\ScimFilterParser\Mode;
use Tmilos\ScimFilterParser\Parser as ScimFilterParserParser;

class Parser {
    public static function parse($input) {
        if($input == null){
            return null;
        }

        $node = (new ScimFilterParserParser(Mode::PATH()))->parse($input);

        return new Path(
            $node,
            $input
        );
    }
}
