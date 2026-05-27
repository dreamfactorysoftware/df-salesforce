<?php

namespace DreamFactory\Core\Salesforce\Tests\Security;

use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Salesforce\Resources\Table;
use PHPUnit\Framework\TestCase;

/**
 * Security: Salesforce Table::buildConditionsStr() must validate $table and
 * $fields as SOQL identifiers before concatenation into the query string.
 *
 * The filter parameter (`?filter=...`) is a SOQL WHERE fragment by design,
 * and Salesforce's REST query endpoint only executes one statement per
 * request, so traditional stacked-query injection is not the threat. The
 * threat is identifier-position injection — a payload arriving where DF
 * expects a bare object/field name that smuggles SOQL keywords or breaks
 * the syntax assumption.
 *
 * After the fix, both identifier slots are validated against
 * `^[A-Za-z][A-Za-z0-9_.]*$` (the Salesforce object/field shape including
 * relationship dots and `__c` custom suffix). Field lists optionally allow
 * `ASC`/`DESC`/`NULLS FIRST`/`NULLS LAST` qualifiers when used in ORDER BY.
 */
class SoqlIdentifierValidationTest extends TestCase
{
    /**
     * @dataProvider validIdentifierProvider
     */
    public function testAcceptsValidSoqlIdentifier(string $value): void
    {
        Table::assertSafeSoqlIdentifier($value);
        $this->assertTrue(true);
    }

    public static function validIdentifierProvider(): array
    {
        return [
            'standard object'  => ['Account'],
            'custom object'    => ['MyCustom__c'],
            'relationship'     => ['Account.Owner.Name'],
            'with digits'      => ['Object123'],
            'underscore'       => ['my_field'],
        ];
    }

    /**
     * @dataProvider invalidIdentifierProvider
     */
    public function testRejectsInvalidSoqlIdentifier(string $value): void
    {
        $this->expectException(BadRequestException::class);
        Table::assertSafeSoqlIdentifier($value);
    }

    public static function invalidIdentifierProvider(): array
    {
        return [
            'with semicolon'   => ['Account; SELECT'],
            'with space'       => ['Account WHERE 1=1'],
            'with quote'       => ["Account' OR '1"],
            'with paren'       => ['Account)'],
            'with dash'        => ['Acc-ount'],
            'starts with number' => ['1Account'],
            'empty'            => [''],
            'only whitespace'  => ['   '],
            'with newline'     => ["Account\nSELECT"],
            'starts with dot'  => ['.Account'],
        ];
    }

    public function testFieldListAcceptsBareList(): void
    {
        Table::assertSafeSoqlFieldList('Id, Name, Owner.Name');
        $this->assertTrue(true);
    }

    public function testFieldListAcceptsOrderByQualifiers(): void
    {
        Table::assertSafeSoqlFieldList('CreatedDate DESC, Name ASC');
        Table::assertSafeSoqlFieldList('Name ASC NULLS FIRST');
        $this->assertTrue(true);
    }

    public function testFieldListRejectsInjectionPayload(): void
    {
        $this->expectException(BadRequestException::class);
        Table::assertSafeSoqlFieldList("Id, Name'; DELETE FROM");
    }

    public function testCallSiteValidatesTableAndFields(): void
    {
        $sourcePath = __DIR__ . '/../../src/Resources/Table.php';
        $this->assertFileExists($sourcePath);
        $contents = file_get_contents($sourcePath);

        $this->assertMatchesRegularExpression(
            '/assertSafeSoqlIdentifier\s*\(\s*\$table/',
            $contents,
            'buildConditionsStr() must validate $table'
        );
        $this->assertMatchesRegularExpression(
            '/assertSafeSoqlFieldList\s*\(\s*\$fields/',
            $contents,
            'buildConditionsStr() must validate $fields'
        );
    }
}
