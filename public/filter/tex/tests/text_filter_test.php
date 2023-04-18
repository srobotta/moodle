<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace filter_tex;

use core\context\system as context_system;

/**
 * Unit tests for text_filter.
 *
 * Test the delimiter parsing used by the tex filter.
 *
 * @package    filter_tex
 * @copyright  2014 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \filter_tex\text_filter
 */
final class text_filter_test extends \advanced_testcase {
    /**
     * Test the delimeter support.
     *
     * @param string $start
     * @param string $end
     * @param bool $filtershouldrun
     * @dataProvider delimiter_provider
     */
    public function test_delimiter_support(
        string $start,
        string $end,
        bool $filtershouldrun,
    ): void {
        $this->resetAfterTest();

        $filter = new text_filter(context_system::instance(), []);

        $pre = 'Some pre text';
        $post = 'Some post text';
        $equation = ' \sum{a^b} ';

        $before = $pre . $start . $equation . $end . $post;

        $after = trim($filter->filter($before));

        if ($filtershouldrun) {
            $this->assertNotEquals($after, $before);
        } else {
            $this->assertEquals($after, $before);
        }
    }

    /**
     * Data provider for delimeters.
     *
     * @return array
     */
    public static function delimiter_provider(): array {
        return [
            // First test the list of supported delimiters.
            ['$$', '$$', true],
            ['\\(', '\\)', true],
            ['\\[', '\\]', true],
            ['[tex]', '[/tex]', true],
            ['<tex>', '</tex>', true],
            ['<tex alt="nonsense">', '</tex>', true],

            // Now test some cases that shouldn't be executed.
            ['<textarea>', '</textarea>', false],
            ['$', '$', false],
            ['(', ')', false],
            ['[', ']', false],
            ['$$', '\\]', false],
        ];
    }

    /**
     * Get a list of terms that are placed inside the tex markers so that the term should be recognized as a tex term
     * and be processed by a tex processor. The array key is what the user provides and the array value is what the
     * system should pass on to the tex processor.
     *
     * @return array
     */
    public static function get_texterms_for_filter(): array {
        return [
            ['alert(\'1\')', 'alert(\'1\')'],
            ['</script><script>alert(\'2\')</script>', 'alert(\'2\')'],
            ['<script>< / scrip t> </s</script>cript>alert(\'3\')</script>', '< / scrip t> alert(\'3\')'],
            ['<img src="x" onerror="alert(\'4\')">', ''],
            ['<img src="data:image/png;base64, iVBORw0KGgoAAJggg==" style="align: left;" onmouseover="alert(\'3\')" />', ''],
            ['<br/>', ''],
            ['<br>', ''],
            ['<hr />', ''],
            ['<select name="foo" data-id="123">', ''],
            ['<a href="#" onmouseenter=\'alert(1);\' someattr>de < f</a>', 'de < f'],
            ['<td >', '<td >'],
            ['f(x) = x^2', 'f(x) = x^2'],
            ['\frac{1}{\sqrt{x}}', '\frac{1}{\sqrt{x}}'],
            ['x\ =\ \frac{\sqrt{144}}{2}\ \times\ (y\ +\ 12)', 'x\ =\ \frac{\sqrt{144}}{2}\ \times\ (y\ +\ 12)'],
            ['3>\frac{ab}{cd}>1', '3>\frac{ab}{cd}>1'],
            ['\[ x\ =\ \frac{\sqrt{144}}{2}\ \times\ (y\ +\ 12) \]', '\[ x\ =\ \frac{\sqrt{144}}{2}\ \times\ (y\ +\ 12) \]'],
            ['<1,3,5>', '<1,3,5>'],
            ['(2,3)>(1,4)', '(2,3)>(1,4)'],
            ['3<4 and 5>\lambda', '3<4 and 5>\lambda'],
            ['<13, 4, 12>', '<13, 4, 12>'],
            ['a<b \cap c>d', 'a<b \cap c>d'],
            // This last testcase is wrong, versus should be preserved however, that makes it more complicated for purging
            // html. Besides in real live you probably use something like the previous example with a special char that
            // starts with a backslash. To circumvent this behaviour, it's always good to add spaces before or after the <>.
            ['a<b versus c>d', 'a<b c>d'],
        ];
    }

    /**
     * Test the tex filter, the rendered tex is inside this tag <script type="math/tex"> </script>.
     *
     * @covers \filter_tex::filter partially
     */
    public function test_filter(): void {
        $this->resetAfterTest();
        $filter = new text_filter(context_system::instance(), []);
        $input = 'some text $$ <a href="#" onmouseenter=\'alert(1);\' someattr>de < f</a> $$ trailed by other text';
        $this->assertStringContainsString(
            '<script type="math/tex"> de < f </script> trailed by other text',
            $filter->filter($input)
        );
    }

    /**
     * Test the filter function that is sanitizing the tex term before it can be used in the HTML inside the <script> tags.
     *
     * @covers \clean_tex_for_html
     * @dataProvider get_texterms_for_filter
     * @param string $in
     * @param string $out
     */
    public function test_strip_html_from_tex($in, $out): void {
        $this->resetAfterTest();
        $class = new \ReflectionClass('\\filter_tex\\text_filter');
        $method = $class->getMethod('strip_html_from_tex');
        $filter = new text_filter(context_system::instance(), []);
        $this->assertEquals($out, $method->invokeArgs($filter, [$in]));
    }
}
