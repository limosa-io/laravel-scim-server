<?php

namespace ArieTimmerman\Laravel\SCIMServer\Parser;

use Tmilos\ScimFilterParser\Mode;
use Tmilos\ScimFilterParser\Parser as ScimFilterParserParser;

class Parser {
    public static function parse($input): ?Path {
        if($input == null){
            return null;
        }

        $node = (new ScimFilterParserParser(Mode::PATH()))->parse($input);

        return new Path(
            $node,
            $input
        );
    }

    public static function parseFilter($input): ?Path {
        if($input == null){
            return null;
        }

        $node = (new ScimFilterParserParser(Mode::FILTER()))->parse($input);

        return new Path(
            $node,
            $input
        );
    }
}
