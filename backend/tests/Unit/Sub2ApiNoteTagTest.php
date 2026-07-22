<?php

namespace Tests\Unit;

use App\Support\Sub2ApiNoteTag;
use PHPUnit\Framework\TestCase;

class Sub2ApiNoteTagTest extends TestCase
{
    public function test_visible_notes_hide_the_audit_tag(): void
    {
        $tag = Sub2ApiNoteTag::make('ADJ-1', 'idem-1');

        $this->assertNull(Sub2ApiNoteTag::visibleNotes($tag));
        $this->assertSame('customer note', Sub2ApiNoteTag::visibleNotes($tag."\ncustomer note"));
        $this->assertSame('legacy note', Sub2ApiNoteTag::visibleNotes(' legacy note '));
    }
}
