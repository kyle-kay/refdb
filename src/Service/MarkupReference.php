<?php

namespace App\Service;

use App\Entity\Journal;
use App\Entity\Lookup;
use App\Entity\LookupMeta;
use Doctrine\ORM\EntityManagerInterface;

class MarkupReference
{

    public function latex(string $reference, ?string $journalName, ?string $type = null, ?string $title = null): string {
        $reference = strip_tags($reference);
        $reference = preg_replace("/ et al./", " \\textit{et al.}", $reference, 1);
        $reference = preg_replace('#doi:(10\.\d{4,9}/[-._;()/:A-Z0-9]+)#i', "\\doi{\$1}", $reference);
        if ($journalName !== null) {
            $reference = str_replace($journalName, "\\textit{" . $journalName . "}", $reference);
        }
        // For books, italicize the title (JACoW style)
        if ($this->isBookType($type) && $title !== null) {
            $reference = str_replace($title, "\\textit{" . $title . "}", $reference);
        }
        $lq = "\xE2\x80\x9C";
        $rq = "\xE2\x80\x9D";
        $leftCount = substr_count($reference, $lq);
        $rightCount = substr_count($reference, $rq);
        if ($leftCount == 1 && $rightCount == 1 && strpos($reference, $lq) < strpos($reference, $rq)) {
            $reference = str_replace($lq, "\\textquotedblleft{", $reference);
            $reference = str_replace($rq, "}\\textquotedblright", $reference);
        }
        return $reference;
    }

    public function word(string $reference, ?string $journalName, ?string $type = null, ?string $title = null): string {
        $reference = preg_replace("/ et al./", " <em>et al.</em>", $reference, 1);
        $reference = preg_replace('#doi:(10\.\d{4,9}/[-._;()/:A-Z0-9]+)#i', "<a href=\"http://doi.org/$1\"><span style=\"font-family:'Liberation Mono'; font-size:8pt;\">$0</span></a>", $reference);
        if ($journalName !== null) {
            $reference = str_replace($journalName, "<em>" . $journalName . "</em>", $reference);
        }
        // For books, italicize the title (JACoW style)
        if ($this->isBookType($type) && $title !== null) {
            $reference = str_replace($title, "<em>" . $title . "</em>", $reference);
        }
        return $reference;
    }

    private function isBookType(?string $type): bool
    {
        return in_array($type, ["book", "monograph", "edited-book", "book-chapter", "reference-book"]);
    }

}
