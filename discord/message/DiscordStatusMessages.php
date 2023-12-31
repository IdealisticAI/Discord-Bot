<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Channel;
use Discord\Parts\User\Member;

class DiscordStatusMessages
{
    private DiscordPlan $plan;

    private const
        WELCOME = 1,
        GOODBYE = 2;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
    }

    public function welcome(Member $member): void
    {
        $serverID = $member->guild_id;
        $userID = $member->id;

        if (!$this->hasCooldown($serverID, $userID, self::WELCOME)
            && !empty($this->plan->channels->getList())) {
            foreach ($this->plan->channels->getList() as $channel) {
                if ($channel->server_id == $serverID
                    && $channel->welcome_message !== null) {
                    if ($channel->whitelist === null) {
                        $channelFound = $this->plan->bot->discord->getChannel($channel->channel_id);

                        if ($channelFound !== null
                            && $channelFound->allowText()) {
                            $this->process($channelFound, $member, $channel, self::WELCOME);
                        }
                    } else if (!empty($this->plan->channels->getWhitelist())) {
                        foreach ($this->plan->channels->getWhitelist() as $whitelist) {
                            if ($whitelist->user_id == $userID
                                && ($whitelist->server_id === null
                                    || $whitelist->server_id === $serverID
                                    && ($whitelist->channel_id === null
                                        || $whitelist->channel_id === $channel->channel_id))) {
                                $channelFound = $this->plan->bot->discord->getChannel($channel->channel_id);

                                if ($channelFound !== null
                                    && $channelFound->allowText()) {
                                    $this->process($channelFound, $member, $channel, self::WELCOME);
                                }
                                break;
                            }
                        }
                    }
                }
            }
        }
    }

    public function goodbye(Member $member): void
    {
        $serverID = $member->guild_id;
        $userID = $member->id;

        if (!$this->hasCooldown($serverID, $userID, self::GOODBYE)
            && !empty($this->plan->channels->getList())) {
            foreach ($this->plan->channels->getList() as $channel) {
                if ($channel->server_id == $serverID
                    && $channel->goodbye_message !== null) {
                    if ($channel->whitelist === null) {
                        $channelFound = $this->plan->bot->discord->getChannel($channel->channel_id);

                        if ($channelFound !== null
                            && $channelFound->allowText()) {
                            $this->process($channelFound, $member, $channel, self::GOODBYE);
                        }
                    } else if (!empty($this->plan->channels->getWhitelist())) {
                        foreach ($this->plan->channels->getWhitelist() as $whitelist) {
                            if ($whitelist->user_id == $userID
                                && ($whitelist->server_id === null
                                    || $whitelist->server_id === $serverID
                                    && ($whitelist->channel_id === null
                                        || $whitelist->channel_id === $channel->channel_id))) {
                                $channelFound = $this->plan->bot->discord->getChannel($channel->channel_id);

                                if ($channelFound !== null
                                    && $channelFound->allowText()) {
                                    $this->process($channelFound, $member, $channel, self::GOODBYE);
                                }
                                break;
                            }
                        }
                    }
                }
            }
        }
    }

    private function process(Channel $channel, Member $member,
                             object  $object,
                             int     $case): void
    {
        switch ($case) {
            case self::WELCOME:
                $this->addCooldown($member->guild_id, $member->id, $case);
                $channel->sendMessage(
                    $this->plan->listener->callStatusMessageImplementation(
                        $object->listener_class,
                        $object->listener_method,
                        $channel,
                        $member,
                        MessageBuilder::new()->setContent(
                            "<@" . $member->id . "> " . $object->welcome_message
                        ),
                        $object,
                        $case
                    )
                );
                break;
            case self::GOODBYE:
                $this->addCooldown($member->guild_id, $member->id, $case);
                $channel->sendMessage(
                    $this->plan->listener->callStatusMessageImplementation(
                        $object->listener_class,
                        $object->listener_method,
                        $channel,
                        $member,
                        MessageBuilder::new()->setContent(
                            $member->username . " " . $object->goodbye_message
                        ),
                        $object,
                        $case
                    )
                );
                break;
            default:
                break;
        }
    }

    private function hasCooldown(int|string $serverID, int|string $userID, int $case): bool
    {
        return !has_memory_cooldown(
            array(__METHOD__, $serverID, $userID, $case),
            "5 minutes", false);
    }

    private function addCooldown(int|string $serverID, int|string $userID, int $case): void
    {
        has_memory_cooldown(
            array(__METHOD__, $serverID, $userID, $case),
            "5 minutes", true, true);
    }
}