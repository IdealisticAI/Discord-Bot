<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Invite;
use Discord\Parts\Channel\Message;
use Discord\Parts\Guild\Guild;
use Discord\Parts\Interactions\Interaction;
use Discord\Parts\Thread\Thread;
use Discord\Parts\User\Member;

class DiscordListener
{
    private DiscordPlan $plan;
    private const
        CREATION_MESSAGE = "/root/discord_bot/listeners/creation/message/",
        CREATION_MODAL = "/root/discord_bot/listeners/creation/modal/",

        IMPLEMENTATION_MESSAGE = "/root/discord_bot/listeners/implementation/message/",
        IMPLEMENTATION_MODAL = "/root/discord_bot/listeners/implementation/modal/",
        IMPLEMENTATION_COMMAND = "/root/discord_bot/listeners/implementation/command/",

        IMPLEMENTATION_USER_TICKETS = "/root/discord_bot/listeners/implementation/user_tickets/",
        IMPLEMENTATION_CHANNEL_COUNTING = "/root/discord_bot/listeners/implementation/channel_counting/",
        IMPLEMENTATION_INVITE_TRACKER = "/root/discord_bot/listeners/implementation/invite_tracker/",
        IMPLEMENTATION_USER_LEVELS = "/root/discord_bot/listeners/implementation/user_level/",
        IMPLEMENTATION_CHANNEL_STATISTICS = "/root/discord_bot/listeners/implementation/channel_statistics/",
        IMPLEMENTATION_REMINDER_MESSAGE = "/root/discord_bot/listeners/implementation/reminder_message/",
        IMPLEMENTATION_STATUS_MESSAGE = "/root/discord_bot/listeners/implementation/status_message/";

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
    }

    public function callMessageImplementation(Interaction    $interaction,
                                              MessageBuilder $messageBuilder,
                                              ?string        $class, ?string $method,
                                              mixed          $objects = null): void
    {
        if ($class !== null && $method !== null) {
            require_once(self::IMPLEMENTATION_MESSAGE . $this->plan->planID . "/" . $class . '.php');
            call_user_func_array(
                array($class, $method),
                array($this->plan, $interaction, $messageBuilder, $objects)
            );
        }
    }

    public function callModalImplementation(Interaction $interaction,
                                            ?string     $class, ?string $method,
                                            mixed       $objects = null): void
    {
        if ($class !== null && $method !== null) {
            require_once(self::IMPLEMENTATION_MODAL . $this->plan->planID . "/" . $class . '.php');
            call_user_func_array(
                array($class, $method),
                array($this->plan, $interaction, $objects)
            );
        }
    }

    public function callMessageBuilderCreation(?Interaction   $interaction,
                                               MessageBuilder $messageBuilder,
                                               ?string        $class, ?string $method): MessageBuilder
    {
        if ($class !== null && $method !== null) {
            require_once(self::CREATION_MESSAGE . $this->plan->planID . "/" . $class . '.php');
            $outcome = call_user_func_array(
                array($class, $method),
                array($this->plan, $interaction, $messageBuilder)
            );
            return $outcome;
        } else {
            return $messageBuilder;
        }
    }

    public function callModalCreation(Interaction $interaction,
                                      array       $actionRows,
                                      ?string     $class, ?string $method): array
    {
        if ($class !== null && $method !== null) {
            require_once(self::CREATION_MODAL . $this->plan->planID . "/" . $class . '.php');
            $outcome = call_user_func_array(
                array($class, $method),
                array($this->plan, $interaction, $actionRows)
            );
            return $outcome;
        } else {
            return $actionRows;
        }
    }

    public function callCommandImplementation(object  $command,
                                              ?string $class, ?string $method): void
    {
        if ($class !== null && $method !== null) {
            require_once(
                self::IMPLEMENTATION_COMMAND
                . (empty($command->plan_id) ? "0" : $command->plan_id)
                . "/" . $class . '.php'
            );
            try {
                $this->plan->bot->discord->listenCommand(
                    $command->command_identification,
                    function (Interaction $interaction) use ($class, $method, $command) {
                        $mute = $this->plan->bot->mute->isMuted($interaction->member, $interaction->channel, DiscordMute::COMMAND);

                        if ($mute !== null) {
                            return $mute->creation_reason;
                        } else if ($command->required_permission !== null
                            && !$this->plan->permissions->hasPermission(
                                $interaction->member,
                                $command->required_permission
                            )) {
                            $this->plan->utilities->acknowledgeCommandMessage(
                                $interaction,
                                MessageBuilder::new()->setContent($command->no_permission_message),
                                $command->ephemeral !== null
                            );
                        } else if ($command->command_reply !== null) {
                            $this->plan->utilities->acknowledgeCommandMessage(
                                $interaction,
                                MessageBuilder::new()->setContent($command->command_reply),
                                $command->ephemeral !== null
                            );
                        } else {
                            call_user_func_array(
                                array($class, $method),
                                array($this->plan, $interaction, $command)
                            );
                        }
                    }
                );
            } catch (Throwable $ignored) {
            }
        }
    }

    public function callTicketImplementation(Interaction $interaction,
                                             ?string     $class, ?string $method,
                                             mixed       $objects): void
    {
        if ($class !== null && $method !== null) {
            require_once(self::IMPLEMENTATION_USER_TICKETS . $this->plan->planID . "/" . $class . '.php');
            call_user_func_array(
                array($class, $method),
                array($this->plan, $interaction, $objects)
            );
        }
    }

    public function callCountingGoalImplementation(?string $class, ?string $method,
                                                   Message $message,
                                                   mixed   $object): void
    {
        if ($class !== null && $method !== null) {
            require_once(self::IMPLEMENTATION_CHANNEL_COUNTING . $this->plan->planID . "/" . $class . '.php');
            call_user_func_array(
                array($class, $method),
                array($this->plan, $message, $object)
            );
        }
    }

    public function callInviteTrackerImplementation(?string $class, ?string $method,
                                                    Invite  $invite): void
    {
        if ($class !== null && $method !== null) {
            require_once(self::IMPLEMENTATION_INVITE_TRACKER . $this->plan->planID . "/" . $class . '.php');
            call_user_func_array(
                array($class, $method),
                array($this->plan, $invite)
            );
        }
    }

    public function callUserLevelsImplementation(?string $class, ?string $method,
                                                 Channel $channel,
                                                 object  $configuration,
                                                 object  $oldTier,
                                                 object  $newTier): void
    {
        if ($class !== null && $method !== null) {
            require_once(self::IMPLEMENTATION_USER_LEVELS . $this->plan->planID . "/" . $class . '.php');
            call_user_func_array(
                array($class, $method),
                array($this->plan, $channel, $configuration, $oldTier, $newTier)
            );
        }
    }

    public function callChannelStatisticsImplementation(?string  $class, ?string $method,
                                                        Guild    $guild,
                                                        ?Channel $channel,
                                                        string   $name,
                                                        object   $object): string
    {
        if ($class !== null && $method !== null) {
            require_once(self::IMPLEMENTATION_CHANNEL_STATISTICS . $this->plan->planID . "/" . $class . '.php');
            return call_user_func_array(
                array($class, $method),
                array($this->plan, $guild, $channel, $name, $object)
            );
        }
        return $name;
    }

    public function callReminderMessageImplementation(?string        $class, ?string $method,
                                                      Channel|Thread $channel,
                                                      MessageBuilder $messageBuilder,
                                                      object         $object): MessageBuilder
    {
        if ($class !== null && $method !== null) {
            require_once(self::IMPLEMENTATION_REMINDER_MESSAGE . $this->plan->planID . "/" . $class . '.php');
            return call_user_func_array(
                array($class, $method),
                array($this->plan, $channel, $messageBuilder, $object)
            );
        }
        return $messageBuilder;
    }

    public function callStatusMessageImplementation(?string        $class, ?string $method,
                                                    Channel        $channel,
                                                    Member         $member,
                                                    MessageBuilder $messageBuilder,
                                                    object         $object,
                                                    int            $case): MessageBuilder
    {
        if ($class !== null && $method !== null) {
            require_once(self::IMPLEMENTATION_STATUS_MESSAGE . $this->plan->planID . "/" . $class . '.php');
            return call_user_func_array(
                array($class, $method),
                array($this->plan, $channel, $member, $messageBuilder, $object, $case)
            );
        }
        return $messageBuilder;
    }
}