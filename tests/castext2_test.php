<?php

// We run tests using STACKs CAS-sessions.
require_once __DIR__ . '/../../stack/tests/fixtures/test_base.php';

// Current requirements are these, if changed update the mapping function.
require_once __DIR__ . '/../stateful/castext2/castext2_evaluatable.class.php';
require_once __DIR__ . '/../../stack/stack/cas/cassession2.class.php';
require_once __DIR__ . '/../../stack/stack/cas/ast.container.class.php';
require_once __DIR__ . '/../../stack/stack/options.class.php';


/**
 * This set of tests tests the behaviour of a CASText implementation.
 *
 * It can also be interpreted as the functional declaration of CASText.
 *
 * @group qtype_stateful
 * @group qtype_stateful_modules
 * @group qtype_stateful_castext_module
 */
class stateful_castext_test extends qtype_stack_testcase {

    // This function maps a given set of CASText code, CASString 
    // style preamble statements and STACK options to the current 
    // implementation and generates the end result.
    // Validation is not being tested, here.
    private function evaluate(string $code, array $preamble=array(), stack_options $options=null): string {
        $statements = array();
        foreach ($preamble as $statement) {
            $statements[] = stack_ast_container::make_from_teacher_source($statement, 'castext-test-case');
        }
        $result = castext2_evaluatable::make_from_source($code, 'castext-test-case');
        $statements[] = $result;
        $session = new stack_cas_session2($statements, $options);

        $session->instantiate();

        return $result->get_rendered();
    }

    // LaTeX-injection "{@value@}" functional requirements:
    //  1. Must result in LaTeX code representing the value given.
    //     Note! Finer details of this need additional tests but are not
    //     really CASText related issues. e.g. extra parentheses.
    //  2. Must allow references to previous code. Both within CASText
    //     and outside.
    //  3. Must support statement level overriding of simplification.
    //  4. Must follow global simplification otherwise.
    //  5. If injected outside LaTeX math-mode must wrap the generated
    //     code to inline math delimiters, otherwise no wrapping.
    //  6. When injecting within math-mode wraps result in extra braces.
    //  7. "string" values are outputted as they are.
    public function test_latex_injection_1() {
        $input = '{@1+2@}, \[{@sqrt(2)@}\]';
        $output = '\(3\), \[{\sqrt{2}}\]';
        $this->assertEquals($output, $this->evaluate($input));
    }

    public function test_latex_injection_2() {
        $input = '{@a@}, {@c:b@}, {@3/9,simp=false@}, {@c@}, {@d@}';
        // Note that last one if we are in global simp:true we just cannot know
        // whether that needs to be protected.
        $preamble = array('a:3/9', 'b:sqrt(2)', 'd:3/9');
        $output = '\(\frac{1}{3}\), \(\sqrt{2}\), \(\frac{3}{9}\), \(\sqrt{2}\), \(\frac{1}{3}\)';
        $this->assertEquals($output, $this->evaluate($input, $preamble));
    }

    public function test_latex_injection_3() {
        $input = '{@a@}, {@3/9@}, {@3/9,simp@}, {@a,simp=false@}, {@a,simp@}';
        $preamble = array('a:3/9');
        $output = '\(\frac{3}{9}\), \(\frac{3}{9}\), \(\frac{1}{3}\), \(\frac{3}{9}\), \(\frac{1}{3}\)';
        $options = new stack_options(array('simplify' => false));
        $this->assertEquals($output, $this->evaluate($input, $preamble, $options));
    }

    public function test_latex_injection_4() {
        $input = ' {@"test string"@} ';
        $output = ' test string ';
        $this->assertEquals($output, $this->evaluate($input));
    }

    // Value-injection "{#value#}" functional requirements:
    //  1. Must result in Maxima code representing the value given.
    //     Equivalent to calling string() in Maxima.
    //  2. Must allow references to previous code. Both within CASText
    //     and outside.
    //  3. Must support statement level overriding of simplification.
    //  4. Must follow global simplification otherwise.
    public function test_value_injection_1() {
        $input = '{#1+2#}, {#sqrt(2)#}';
        $output = '3, sqrt(2)';
        $this->assertEquals($output, $this->evaluate($input));
    }

    public function test_value_injection_2() {
        $input = '{#a#}, {#c:b#}, {#3/9,simp=false#}, {#c#}, {#d#}';
        // Note that last one if we are in global simp:true we just cannot know
        // whether that needs to be protected.
        $preamble = array('a:3/9', 'b:sqrt(2)', 'd:3/9');
        $output = '1/3, sqrt(2), 3/9, sqrt(2), 1/3';
        $this->assertEquals($output, $this->evaluate($input, $preamble));
    }

    public function test_value_injection_3() {
        $input = '{#a#}, {#3/9#}, {#3/9,simp#}, {#a,simp=false#}, {#a,simp#}';
        $preamble = array('a:3/9');
        $output = '3/9, 3/9, 1/3, 3/9, 1/3';
        $options = new stack_options(array('simplify' => false));
        $this->assertEquals($output, $this->evaluate($input, $preamble, $options));
    }

    // STACK-options level requirements 1/3:
    // Tuning LaTeX-injection multiplication sign.
    public function test_latex_injection_multiplicationsign_dot() {
        $input = '{@a@}, {@pi*x^2@}';
        $preamble = array('a:x*y*z');
        $output = '\(x\cdot y\cdot z\), \(\pi\cdot x^2\)';
        $options = new stack_options(array('multiplicationsign' => 'dot'));
        $this->assertEquals($output, $this->evaluate($input, $preamble, $options));
    }

    public function test_latex_injection_multiplicationsign_cross() {
        $input = '{@a@}, {@pi*x^2@}';
        $preamble = array('a:x*y*z');
        $output = '\(x\times y\times z\), \(\pi\times x^2\)';
        $options = new stack_options(array('multiplicationsign' => 'cross'));
        $this->assertEquals($output, $this->evaluate($input, $preamble, $options));
    }

    public function test_latex_injection_multiplicationsign_none() {
        $input = '{@a@}, {@pi*x^2@}';
        $preamble = array('a:x*y*z');
        $output = '\(x\,y\,z\), \(\pi\,x^2\)';
        $options = new stack_options(array('multiplicationsign' => 'none'));
        $this->assertEquals($output, $this->evaluate($input, $preamble, $options));
    }

    // STACK-option level requirements 2/3:
    // Tuning LaTeX-injection inverse trigonometric functions.
    public function test_latex_injection_inversetrig_acos() {
        $input = '\({@acos(alpha)@}, {@asin(alpha)@}, {@a@}\)';
        $preamble = array('a:asech(alpha)');
        $output = '\({{\rm acos}\left( \alpha \right)}, {{\rm asin}\left( \alpha \right)}, {{\rm asech}\left( \alpha \right)}\)';
        $options = new stack_options(array('inversetrig' => 'acos'));
        $this->assertEquals($output, $this->evaluate($input, $preamble, $options));
    }

    public function test_latex_injection_inversetrig_cos_1() {
        $input = '\({@acos(alpha)@}, {@asin(alpha)@}, {@a@}\)';
        $preamble = array('a:asech(alpha)');
        $output = '\({\cos^{-1}\left( \alpha \right)}, {\sin^{-1}\left( \alpha \right)}, {{\rm sech}^{-1}\left( \alpha \right)}\)';
        $options = new stack_options(array('inversetrig' => 'cos-1'));
        $this->assertEquals($output, $this->evaluate($input, $preamble, $options));
    }

    public function test_latex_injection_inversetrig_arccos() {
        $input = '\({@acos(alpha)@}, {@asin(alpha)@}, {@a@}\)';
        $preamble = array('a:asech(alpha)');
        $output = '\({\arccos \left( \alpha \right)}, {\arcsin \left( \alpha \right)}, {{\rm arcsech}\left( \alpha \right)}\)';
        $options = new stack_options(array('inversetrig' => 'arccos'));
        $this->assertEquals($output, $this->evaluate($input, $preamble, $options));
    }

    // STACK-option level requirements 3/3:
    // Tuning LaTeX-injection matrix parenthesis.
    public function test_latex_injection_matrixparens_brackets() {
        $input = '{@matrix([1,0],[0,1])@}';
        $preamble = array();
        $output = '\(\left[\begin{array}{cc} 1 & 0 \\\\ 0 & 1 \end{array}\right]\)';
        $options = new stack_options(array('matrixparens' => '['));
        $this->assertEquals($output, $this->evaluate($input, $preamble, $options));
    }

    public function test_latex_injection_matrixparens_parens() {
        $input = '{@matrix([1,0],[0,1])@}';
        $preamble = array();
        $output = '\(\left(\begin{array}{cc} 1 & 0 \\\\ 0 & 1 \end{array}\right)\)';
        $options = new stack_options(array('matrixparens' => '('));
        $this->assertEquals($output, $this->evaluate($input, $preamble, $options));
    }

    public function test_latex_injection_matrixparens_braces() {
        $input = '{@matrix([1,0],[0,1])@}';
        $preamble = array();
        $output = '\(\left\{\begin{array}{cc} 1 & 0 \\\\ 0 & 1 \end{array}\right\}\)';
        $options = new stack_options(array('matrixparens' => '{'));
        $this->assertEquals($output, $this->evaluate($input, $preamble, $options));
    }

    public function test_latex_injection_matrixparens_none() {
        $input = '{@matrix([1,0],[0,1])@}';
        $preamble = array();
        $output = '\(\begin{array}{cc} 1 & 0 \\\\ 0 & 1 \end{array}\)';
        $options = new stack_options(array('matrixparens' => ''));
        $this->assertEquals($output, $this->evaluate($input, $preamble, $options));
    }

    public function test_latex_injection_matrixparens_pipe() {
        $input = '{@matrix([1,0],[0,1])@}';
        $preamble = array();
        $output = '\(\left|\begin{array}{cc} 1 & 0 \\\\ 0 & 1 \end{array}\right|\)';
        $options = new stack_options(array('matrixparens' => '|'));
        $this->assertEquals($output, $this->evaluate($input, $preamble, $options));
    }

    // Block-system "define"-block, functional requirements:
    //  1. Allow inline changes to any value.
    //  2. Handle simplification.
    //  3. Single block may redefine same value, needs to respect
    //     declaration order.
    public function test_blocks_define() {
        $input = '{#a#}, [[ define a="a+1" a="a*a" b="3/9" c="3/9,simp"/]] {#a#} {#b#} {#b,simp#} {#c#}';
        $preamble = array('a:x');
        $output = 'x,  (x+1)*(x+1) 3/9 1/3 1/3';
        $options = new stack_options(array('simplify' => false));
        $this->assertEquals($output, $this->evaluate($input, $preamble, $options));
    }

    // Block-system "if"-block, functional requirements:
    //  1. Conditional evaluation and display of contents.
    //  2. Else and else if behaviour.
    //  3. Maxima if equivalent conditions.
    public function test_blocks_if_1() {
        $input = '{#a#}, [[ if test="a=x" ]]yes[[ else ]]no[[define a="3"/]][[/if]], [[ if test="a=3"]]maybe[[/ if ]]';
        $preamble = array('a:x');
        $output = 'x, yes, ';
        $options = new stack_options(array('simplify' => false));
        $this->assertEquals($output, $this->evaluate($input, $preamble, $options));
    }

    public function test_blocks_if_2() {
        $input = '{#a#}, [[ if test="a=x" ]]yes[[define a="3"/]][[ else ]]no[[/if]], [[ if test="a=3"]]maybe[[/ if ]]';
        $preamble = array('a:x');
        $output = 'x, yes, maybe';
        $options = new stack_options(array('simplify' => true));
        $this->assertEquals($output, $this->evaluate($input, $preamble, $options));
    }

    public function test_blocks_if_3() {
        $input = '{#a#}, [[ if test="a=x" ]]yes[[define a="3"/]][[ else ]]no[[/if]], [[ if test="a=x"]]no[[elif test="a=3"]]maybe[[/ if ]]';
        $preamble = array('a:x');
        $output = 'x, yes, maybe';
        $options = new stack_options(array('simplify' => true));
        $this->assertEquals($output, $this->evaluate($input, $preamble, $options));
    }

    public function test_blocks_if_4() {
        $input = '{#a#}, [[ if test="a=x" ]]yes[[if test="b=y"]][[define b="x"/]][[/if]][[ else ]]no[[/if]], {#b#}';
        $preamble = array('a:x', 'b:y');
        $output = 'x, yes, x';
        $options = new stack_options(array('simplify' => true));
        $this->assertEquals($output, $this->evaluate($input, $preamble, $options));
    }

    // Block-system "foreach"-block, functional requirements:
    //  1. Iterates over elements of a list or a set assigning the values
    //     to the defined variable.
    //  2. Can iterate over multiple such things simultaneously, but limits
    //     to the length of the shortest one.
    //  3. Simplification is not perfectly maintained as indefinite depth is 
    //     not reasonably maintainable. Applying simplification even when its 
    //     off globaly is supportted but not disabling simplification.
    //  4. In the case of sets the ordering is not well defined.
    public function test_blocks_foreach_1() {
        $input = '[[ foreach foo="a"]][[ foreach bar="foo"]]{#bar#}, [[/foreach]] - [[/foreach]]';
        $preamble = array('a:[[1,1+1,1+1+1],[1,2,3]]');
        $output = '1, 1+1, 1+1+1,  - 1, 2, 3,  - ';
        $options = new stack_options(array('simplify' => false));
        $this->assertEquals($output, $this->evaluate($input, $preamble, $options));
    }

    public function test_blocks_foreach_2() {
        $input = '[[ foreach foo="a"]][[ foreach bar="foo"]]{#bar#}, [[/foreach]] - [[/foreach]]';
        $preamble = array('a:[{1,1+1,1+1+1},{3,2,1}]');
        $output = '1, 2, 3,  - 1, 2, 3,  - ';
        $options = new stack_options(array('simplify' => true));
        $this->assertEquals($output, $this->evaluate($input, $preamble, $options));
    }

    public function test_blocks_foreach_3() {
        $input = '[[ foreach foo="a"]][[ foreach bar="foo,simp"]]{#bar#}, [[/foreach]] - [[/foreach]]';
        $preamble = array('a:[[1,1+1,1+1+1],[1,2,3]]');
        $output = '1, 2, 3,  - 1, 2, 3,  - ';
        $options = new stack_options(array('simplify' => false));
        $this->assertEquals($output, $this->evaluate($input, $preamble, $options));
    }

    public function test_blocks_foreach_4() {
        $input = '[[ foreach foo="a" bar="b"]]{@foo^bar@}, [[/foreach]]';
        $preamble = array('a:[1,2,3,4]', 'b:[x,y,z]');
        $output = '\(1^{x}\), \(2^{y}\), \(3^{z}\), ';
        $options = new stack_options(array('simplify' => false));
        $this->assertEquals($output, $this->evaluate($input, $preamble, $options));
    }

    // Block-system "comment"-block, functional requirements:
    //  1. Comments out itself and contents.
    //  2. Even if contents are invalid or incomplete.
    public function test_blocks_comment() {
        $input = '1[[ comment]] [[ foreach bar="foo"]] {#y@} [[/comment]]2';
        $output = '12';
        $this->assertEquals($output, $this->evaluate($input));
    }

    // Block-system "escape"-block, functional requirements:
    //  1. Escapes the contents so that they will not be processed.
    //  2. Outputs contents as they are.
    public function test_blocks_escape() {
        $input = '1[[ escape ]] [[ foreach bar="foo"]] {#y@} [[/escape]]2';
        $output = '1 [[ foreach bar="foo"]] {#y@} 2';
        $this->assertEquals($output, $this->evaluate($input));
    }

    // Low level tuning, features that are not strictly CASText:
    // Use of texput().
    public function test_texput_1() {
        $input = '\({@foo@}\)';
        $preamble = array('texput(foo, "\\\\frac{foo}{bar}")');
        $output = '\({\frac{foo}{bar}}\)';
        $this->assertEquals($output, $this->evaluate($input, $preamble));
    }   

    public function test_texput_2() {
        $input = '{@x^2+foo(a,sqrt(b))@}';
        $preamble = array('footex(e):=block([a,b],[a,b]:args(e),sconcat(tex1(a)," \\\\rightarrow ",tex1(b)))',
            'texput(foo, footex)');
        $output = '\(x^2+a \rightarrow \sqrt{b}\)';
        $this->assertEquals($output, $this->evaluate($input, $preamble));
    }

    // stackfltfmt for presentation of floating point numbers.
    public function test_stackfltfmt() {
        $input = '{@a@}, {@(stackfltfmt:"~f",a)@}';
        $preamble = array('stackfltfmt:"~e"', 'a:0.000012');
        $output = '\(1.2e-5\), \(0.000012\)';
        $this->assertEquals($output, strtolower($this->evaluate($input, $preamble)));
    }

    // stackintfmt for presentation of integers.    
    public function test_stackintfmt() {
        $input = '{@(stackintfmt:"~:r",a)@}, {@(stackintfmt:"~@R",a)@}';
        $preamble = array('a:1998');
        $output = '\(\mbox{one thousand nine hundred ninety-eighth}\), \(MCMXCVIII\)';
        $this->assertEquals($output, $this->evaluate($input, $preamble));
    }

    // Inline fractions using stack_disp_fractions("i").
    public function test_stack_disp_fractions() {
        $input = '{@(stack_disp_fractions("i"),a/b)@}, {@(stack_disp_fractions("d"),a/b)@}';
        $output = '\({a}/{b}\), \(\frac{a}{b}\)';
        $this->assertEquals($output, $this->evaluate($input));
    }
}
