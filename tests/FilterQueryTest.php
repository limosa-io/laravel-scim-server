<?php

namespace ArieTimmerman\Laravel\SCIMServer\Tests;

use ArieTimmerman\Laravel\SCIMServer\Tests\Model\Group;
use ArieTimmerman\Laravel\SCIMServer\Tests\Model\User;

class FilterQueryTest extends TestCase
{
    public function testValuePathFilterOnMembers()
    {
        $user = User::with('groups')->has('groups')->firstOrFail();
        $group = $user->groups()->firstOrFail();

        $filter = rawurlencode(sprintf('members[value eq "%s"]', $user->id));

        $response = $this->get("/scim/v2/Groups?filter={$filter}&count=200");
        $response->assertStatus(200);

        $ids = collect($response->json('Resources'))->pluck('id');

        $this->assertTrue(
            $ids->contains((string)$group->id),
            'Expected filtered response to contain the group that has the user as a member.'
        );
    }

    public function testNegationFilterExcludesActiveUser()
    {
        $activeUser = User::firstOrFail();
        $activeUser->active = true;
        $activeUser->save();

        $filter = rawurlencode('not (active eq true)');

        $response = $this->get("/scim/v2/Users?filter={$filter}&count=200");
        $response->assertStatus(200);

        $ids = collect($response->json('Resources'))->pluck('id');

        $this->assertFalse(
            $ids->contains((string)$activeUser->id),
            'Active user should be excluded by negation filter.'
        );
    }

    public function testValuePathNegationMatchesRFCSection341(): void
    {
        $user = User::with('groups')->has('groups')->firstOrFail();
        $groupWithUser = $user->groups()->firstOrFail();

        $groupWithoutUser = factory(Group::class)->create();

        $filter = rawurlencode(sprintf('not (members[value eq "%s"])', $user->id));

        $response = $this->get("/scim/v2/Groups?filter={$filter}&count=200");
        $response->assertStatus(200);

        $ids = collect($response->json('Resources'))->pluck('id');

        $this->assertFalse(
            $ids->contains((string)$groupWithUser->id),
            'Groups containing the user should be excluded when using not(valuePath) filter.'
        );
        $this->assertTrue(
            $ids->contains((string)$groupWithoutUser->id),
            'Groups without the user should remain when using not(valuePath) filter.'
        );
    }

    public function testMultiConditionFilterCombinesCriteriaWithAnd(): void
    {
        $matchingUser = factory(User::class)->create([
            'name' => 'te-matching-user',
            'formatted' => 'te matching formatted',
            'email' => 'matching-user@example.com',
        ]);

        $formattedOnlyUser = factory(User::class)->create([
            'name' => 'AlphaName',
            'formatted' => 'te formatted only',
            'email' => 'formatted-only@example.com',
        ]);

        $userNameOnlyUser = factory(User::class)->create([
            'name' => 'te username only',
            'formatted' => 'AlphaDisplay',
            'email' => 'username-only@example.com',
        ]);

        $filter = rawurlencode('name.formatted co "te" and userName co "te"');

        $response = $this->get("/scim/v2/Users?filter={$filter}&count=200");
        $response->assertStatus(200);

        $ids = collect($response->json('Resources'))->pluck('id');

        $this->assertTrue(
            $ids->contains((string)$matchingUser->id),
            'Expected user that matches both conditions to be returned.'
        );
        $this->assertFalse(
            $ids->contains((string)$formattedOnlyUser->id),
            'User matching only the formatted condition should be excluded.'
        );
        $this->assertFalse(
            $ids->contains((string)$userNameOnlyUser->id),
            'User matching only the userName condition should be excluded.'
        );
    }

    public function testExtensionAttributeFilterMatchesEmployeeNumber(): void
    {
        $matchingUser = factory(User::class)->create([
            'employeeNumber' => '1234',
        ]);

        $nonMatchingUser = factory(User::class)->create([
            'employeeNumber' => '5678',
        ]);

        $filter = rawurlencode('urn:ietf:params:scim:schemas:extension:enterprise:2.0:User:employeeNumber eq "1234"');

        $response = $this->get("/scim/v2/Users?filter={$filter}&count=200");
        $response->assertStatus(200);

        $ids = collect($response->json('Resources'))->pluck('id');

        $this->assertTrue(
            $ids->contains((string)$matchingUser->id),
            'Expected filter to return the user matching the enterprise extension employeeNumber.'
        );

        $this->assertFalse(
            $ids->contains((string)$nonMatchingUser->id),
            'Filter should exclude users whose enterprise extension employeeNumber does not match.'
        );
    }
}
