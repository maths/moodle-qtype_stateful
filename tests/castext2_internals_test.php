<?php

// These tests do not declare castext2 requirements they just test 
// the implementation. Do not port these over to castext3.
require_once __DIR__ . '/../../stack/tests/fixtures/test_base.php';

require_once __DIR__ . '/../stateful/castext2/utils.php';

/**
 * This set of tests tests some internal logic.
 *
 * @group qtype_stateful
 * @group qtype_stateful_modules
 * @group qtype_stateful_castext_module
 */
class stateful_castext_internals_test extends qtype_stack_testcase {


    public function test_parser() {
        $parser = new CTP_Parser();
        $code   = '[[ if test="0"]] {#1#} {@2@}[[/if]]';
        $ast    = $parser->parse($code);

        // Parsers will alway wrap the contents into a Root-object.
        // Even if we have only one thing in it.
        $this->assertTrue($ast instanceof CTP_Root);
        $this->assertEquals(1, count($ast->items));
        $this->assertTrue($ast->items[0] instanceof CTP_Block);

        $block = $ast->items[0];
        $this->assertEquals(1, count($block->parameters));

        // Parameters are often just strings as one can use either quotes
        // and the escapes are handled by the string object.
        $this->assertTrue($block->parameters['test'] instanceof CTP_String);
        $this->assertEquals('0', $block->parameters['test']->value);
        $this->assertEquals('if', $block->name);

        // Block contents is just a list of nodes.
        $this->assertEquals(4, count($block->contents));
        $this->assertTrue($block->contents[0] instanceof CTP_Raw);
        $this->assertEquals(' ', $block->contents[0]->value);

        $this->assertTrue($block->contents[1] instanceof CTP_Block);
        $this->assertEquals('raw', $block->contents[1]->name);
        $this->assertEquals('1', $block->contents[1]->contents[0]->value);

        $this->assertTrue($block->contents[2] instanceof CTP_Raw);
        $this->assertEquals(' ', $block->contents[2]->value);

        $this->assertTrue($block->contents[3] instanceof CTP_Block);
        $this->assertEquals('latex', $block->contents[3]->name);
        $this->assertEquals('2', $block->contents[3]->contents[0]->value);
    }

    public function test_ioblockextensions() {
        $parser = new CTP_Parser();
        $code   = '[[list_errors:ans1,ans2]][[ whatever : ans3 ]]';
        $ast    = $parser->parse($code);

        $this->assertTrue($ast instanceof CTP_Root);
        $this->assertEquals(2, count($ast->items));
        $this->assertTrue($ast->items[0] instanceof CTP_IOBlock);
        $this->assertTrue($ast->items[1] instanceof CTP_IOBlock);

        $this->assertEquals('list_errors', $ast->items[0]->channel);
        $this->assertEquals('whatever', $ast->items[1]->channel);
        $this->assertEquals('ans1,ans2', $ast->items[0]->variable);
        $this->assertEquals('ans3', $ast->items[1]->variable);
    }

    public function test_math_paint_1() {
        $parser = new CTP_Parser();
        $code   = '\({#1#}\) {@3@} \[{@5@}\] \begin{equation}{@7@} \end{equation} {#9#}';
        $ast    = $parser->parse($code);

        foreach ($ast->items as $item) {
            $this->assertFalse($item->mathmode);
        }

        $ast = castext2_parser_utils::math_paint($ast, $code);

        $this->assertTrue($ast->items[1]->mathmode);
        $this->assertFalse($ast->items[3]->mathmode);
        $this->assertTrue($ast->items[5]->mathmode);
        $this->assertTrue($ast->items[7]->mathmode);
        $this->assertFalse($ast->items[9]->mathmode);
    }

    public function test_math_paint_2() {
        $parser = new CTP_Parser();
        $code   = '<p>[[commonstring key="your_answer_interpreted_as"/]]</p>';
        $code  .= '[[if test="stringp(ans1)"]]<p style="text-align:center">{@false@}</p>';
        $code  .= '[[else]]\[{@true@}\][[/if]]';
        $ast    = $parser->parse($code);

        
        $check  = function($node) {
            if ($node instanceof CTP_Block) {
                if ($node->name === 'raw' || $node->name === 'latex') {
                    if ($node->contents[0]->value === 'true') {
                        $this->assertTrue($node->mathmode);
                    } else {
                        $this->assertFalse($node->mathmode);
                    }
                }
            }
            return true;
        };

        $ast = castext2_parser_utils::math_paint($ast, $code);      
        $ast->callbackRecurse($check);
    }


}