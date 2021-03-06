<?php

namespace SilverStripe\Upgrader\Tests\UpgradeRule\PHP\Visitor\Warnings;

use SilverStripe\Upgrader\Tests\UpgradeRule\PHP\Visitor\BaseVisitorTest;
use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\Warnings\MethodWarningsVisitor;
use SilverStripe\Upgrader\Util\ApiChangeWarningSpec;
use SilverStripe\Upgrader\Util\MutableSource;

class MethodWarningsVisitorTest extends BaseVisitorTest
{
    /**
     * @runInSeparateProcess
     */
    public function testGlobalMethod()
    {
        $this->scaffoldMockClass('SomeNamespace\SomeClass');
        $this->scaffoldMockClass('GlobalClass');

        // mock myclass
        $myClass = <<<PHP
<?php

use SomeNamespace\SomeClass;

class MyClass
{
    public function otherMethod()
    {
        return true;
    }
    
    public function removedMethod()
    {
        // Should be ignored
        \$removedMethod = true;
        return \$removedMethod;
    }

    public function useRemovedMethod()
    {
        \$obj = new stdClass();
        \$obj->removedMethod();
        
        GlobalClass::removedMethod();
        SomeClass::removedMethod();
    }
}
PHP;

        $input = $this->getMockFile($myClass);
        $source = new MutableSource($input->getContents());
        $visitor = new MethodWarningsVisitor([
            new ApiChangeWarningSpec('removedMethod()', [
                'message' => 'Test global method',
                'replacement' => 'newMethod',
            ])
        ], $source, $input);

        $this->traverseWithVisitor($source, $input, $visitor);

        $warnings = $visitor->getWarnings();
        $this->assertCount(4, $warnings);

        $this->assertContains('Test global method', $warnings[0]->getMessage());
        $this->assertContains('function removedMethod', $this->getLineForWarning($myClass, $warnings[0]));

        $this->assertContains('Test global method', $warnings[1]->getMessage());
        $this->assertContains('$obj->removedMethod()', $this->getLineForWarning($myClass, $warnings[1]));

        $this->assertContains('Test global method', $warnings[2]->getMessage());
        $this->assertContains('GlobalClass::removedMethod()', $this->getLineForWarning($myClass, $warnings[2]));

        $this->assertContains('Test global method', $warnings[3]->getMessage());
        $this->assertContains('SomeClass::removedMethod();', $this->getLineForWarning($myClass, $warnings[3]));

        // Ensure rewrite works
        $newClass = <<<PHP
<?php

use SomeNamespace\SomeClass;

class MyClass
{
    public function otherMethod()
    {
        return true;
    }
    
    public function newMethod()
    {
        // Should be ignored
        \$removedMethod = true;
        return \$removedMethod;
    }

    public function useRemovedMethod()
    {
        \$obj = new stdClass();
        \$obj->newMethod();
        
        GlobalClass::newMethod();
        SomeClass::newMethod();
    }
}
PHP;
        $actualClass = $source->getModifiedString();
        $this->assertNotEquals($source->getOrigString(), $actualClass);
        $this->assertEquals($newClass, $actualClass);
    }

    /**
     * @runInSeparateProcess
     */
    public function testStaticMethodWithClassQualifier()
    {
        // mock someclass
        $someclass = <<<PHP
<?php
namespace SomeNamespace;
class SomeClass {}
PHP;
        $this->getMockFile($someclass, 'SomeClass.php');

        // mock someclass
        $globalClass = <<<PHP
<?php
class GlobalClass {}
PHP;
        $this->getMockFile($globalClass, 'GlobalClass.php');

        $myClass = <<<PHP
<?php

use SomeNamespace\SomeClass;

class MyClass
{
    public function staticInvocation()
    {
        \$foo = GlobalClass::removedMethod();
    }
    
    public function staticNamespacedInvocation()
    {
        return SomeClass::removedMethod();
    }
}
PHP;

        $input = $this->getMockFile($myClass);
        $source = new MutableSource($input->getContents());
        $visitor = new MethodWarningsVisitor([
            new ApiChangeWarningSpec(
                'GlobalClass::removedMethod()',
                [
                    'message' => 'Error in GlobalClass::removedMethod()',
                    'replacement' => 'newGlobalMethod',
                ]
            ),
            new ApiChangeWarningSpec(
                'SomeNamespace\\SomeClass::removedMethod()',
                [
                    'message' => 'Error in SomeNamespace\\SomeClass::removedMethod()',
                    'replacement' => 'anotherNewMethod',
                ]
            ),
        ], $source, $input);

        $this->traverseWithVisitor($source, $input, $visitor);

        $warnings = $visitor->getWarnings();
        $this->assertCount(2, $warnings);

        $this->assertContains('Error in GlobalClass::removedMethod()', $warnings[0]->getMessage());
        $this->assertContains('$foo = GlobalClass::removedMethod();', $this->getLineForWarning($myClass, $warnings[0]));

        $this->assertContains('Error in SomeNamespace\\SomeClass::removedMethod()', $warnings[1]->getMessage());
        $this->assertContains('return SomeClass::removedMethod()', $this->getLineForWarning($myClass, $warnings[1]));

        // Ensure rewrite works
        $newClass = <<<PHP
<?php

use SomeNamespace\SomeClass;

class MyClass
{
    public function staticInvocation()
    {
        \$foo = GlobalClass::newGlobalMethod();
    }
    
    public function staticNamespacedInvocation()
    {
        return SomeClass::anotherNewMethod();
    }
}
PHP;
        $actualClass = $source->getModifiedString();
        $this->assertNotEquals($source->getOrigString(), $actualClass);
        $this->assertEquals($newClass, $actualClass);
    }

    /**
     * @runInSeparateProcess
     */
    public function testIgnoresDynamic()
    {
        $myClass = <<<PHP
<?php

use SomeNamespace\SomeClass;

class MyClass
{
    public function staticInvocation()
    {
        \$match = SomeClass::removedMethod();
        \$noMatch = SomeClass::\$removedMethod();
    }
    
    public function instanceInvocation()
    {
        \$match = new SomeClass();
        \$match->removedMethod();
        
        \$noMatch = new SomeClass();
        \$noMatch->\$removedMethod();
    }
}
PHP;

        $input = $this->getMockFile($myClass);
        $source = new MutableSource($input->getContents());
        $visitor = new MethodWarningsVisitor([
            new ApiChangeWarningSpec('removedMethod()', [
                'message' =>'Error in removedMethod()',
                'replacement' => 'newMethod',
            ])
        ], $source, $input);

        $this->traverseWithVisitor($source, $input, $visitor);

        $warnings = $visitor->getWarnings();
        $this->assertCount(2, $warnings);

        $this->assertContains('Error in removedMethod()', $warnings[0]->getMessage());
        $this->assertContains('$match = SomeClass::removedMethod()', $this->getLineForWarning($myClass, $warnings[0]));

        $this->assertContains('Error in removedMethod', $warnings[1]->getMessage());
        $this->assertContains('$match->removedMethod()', $this->getLineForWarning($myClass, $warnings[1]));

        // Ensure rewrite works
        $newClass = <<<PHP
<?php

use SomeNamespace\SomeClass;

class MyClass
{
    public function staticInvocation()
    {
        \$match = SomeClass::newMethod();
        \$noMatch = SomeClass::\$removedMethod();
    }
    
    public function instanceInvocation()
    {
        \$match = new SomeClass();
        \$match->newMethod();
        
        \$noMatch = new SomeClass();
        \$noMatch->\$removedMethod();
    }
}
PHP;
        $actualClass = $source->getModifiedString();
        $this->assertNotEquals($source->getOrigString(), $actualClass);
        $this->assertEquals($newClass, $actualClass);
    }
}
