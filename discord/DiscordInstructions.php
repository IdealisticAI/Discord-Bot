<?php

class DiscordInstructions
{

    private DiscordPlan $plan;
    private array $instructions, $placeholders;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->instructions = get_sql_query(
            BotDatabaseTable::BOT_LOCAL_INSTRUCTIONS,
            null,
            array(
                array("deletion_date", null),
                array("application_id", $this->plan->applicationID),
                null,
                array("plan_id", "IS", null, 0),
                array("plan_id", "=", $this->plan->planID, 0),
                $this->plan->family !== null ? array("family", $this->plan->family) : "",
                null,
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            ),
            "plan_id ASC, priority DESC"
        );
        $this->placeholders = get_sql_query(
            BotDatabaseTable::BOT_INSTRUCTION_PLACEHOLDERS,
            null,
            array(
                array("deletion_date", null),
                null,
                array("plan_id", "IS", null, 0),
                array("plan_id", $this->plan->planID),
                null,
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );
    }

    public function replace(array  $messages, ?object $object = null,
                            string $placeholderStart = DiscordProperties::DEFAULT_PLACEHOLDER_START,
                            string $placeholderMiddle = DiscordProperties::DEFAULT_PLACEHOLDER_MIDDLE,
                            string $placeholderEnd = DiscordProperties::DEFAULT_PLACEHOLDER_END): array
    {
        if (!empty($this->placeholders) && !empty($object)) {
            foreach ($messages as $arrayKey => $message) {
                if ($message === null) {
                    $messages[$arrayKey] = "";
                }
            }
            foreach ($this->placeholders as $placeholder) {
                if (isset($object->{$placeholder->placeholder})) {
                    $value = $object->{$placeholder->code_field};
                } else if ($placeholder->dynamic !== null) {
                    $keyWord = explode($placeholderMiddle, $placeholder->placeholder, 3);
                    $limit = sizeof($keyWord) === 2 ? $keyWord[1] : 0;

                    switch ($keyWord[0]) {
                        case "publicInstructions":
                            $value = $this->getPublic($object->userID, $limit);
                            break;
                        case "botReplies":
                            $value = $this->plan->conversation->getReplies($object->userID, $limit, false);
                            break;
                        case "botMessages":
                            $value = $this->plan->conversation->getMessages($object->userID, $limit, false);
                            break;
                        case "allMessages":
                            $value = $this->plan->conversation->getConversation($object->userID, $limit, false);
                            break;
                        default:
                            $value = "";
                            break;
                    }
                } else {
                    $value = "";
                }

                if (is_array($value)) {
                    $array = $value;
                    $value = "";

                    foreach ($array as $row) {
                        $value .= $row . DiscordProperties::NEW_LINE;
                    }
                    if (!empty($value)) {
                        $value = substr($value, 0, -strlen(DiscordProperties::NEW_LINE));
                    }
                }
                $object->placeholderArray[] = $value;

                if ($placeholder->include_previous !== null
                    && $placeholder->include_previous > 0) {
                    $size = sizeof($object->placeholderArray);

                    for ($position = 1; $position <= $placeholder->include_previous; $position++) {
                        $modifiedPosition = $size - $position;

                        if ($modifiedPosition >= 0) {
                            $positionValue = $object->placeholderArray[$modifiedPosition];

                            foreach ($messages as $arrayKey => $message) {
                                if (!empty($message)) {
                                    $messages[$arrayKey] = str_replace(
                                        $placeholderStart . $placeholder->placeholder . $placeholderEnd,
                                        $positionValue,
                                        $message
                                    );
                                }
                            }
                        } else {
                            break;
                        }
                    }
                }
                foreach ($messages as $arrayKey => $message) {
                    if (!empty($message)) {
                        $messages[$arrayKey] = str_replace(
                            $placeholderStart . $placeholder->placeholder . $placeholderEnd,
                            $value,
                            $message
                        );
                    }
                }
            }
        }
        return $messages;
    }

    public function build(object $object): ?string
    {
        if (!empty($this->instructions)) {
            $information = "";
            $disclaimer = "";

            foreach ($this->instructions as $instruction) {
                $replacements = $this->replace(
                    array(
                        $instruction->information,
                        $instruction->disclaimer
                    ),
                    $object,
                    $instruction->placeholder_start,
                    $instruction->placeholder_middle,
                    $instruction->placeholder_end
                );
                $information .= $replacements[0];
                $disclaimer .= $replacements[1];
            }
            if ($this->plan->strictReply) {
                $information = ($this->plan->requireMention
                        ? DiscordProperties::STRICT_REPLY_INSTRUCTIONS_WITH_MENTION
                        : DiscordProperties::STRICT_REPLY_INSTRUCTIONS)
                    . DiscordProperties::NEW_LINE . DiscordProperties::NEW_LINE . $information;
            }
            return $information
                . (!empty($disclaimer)
                    ? DiscordSyntax::HEAVY_CODE_BLOCK . $disclaimer . DiscordSyntax::HEAVY_CODE_BLOCK
                    : "");
        }
        return null;
    }

    public function getObject($serverID, $serverName,
                              $channelID, $channelName,
                              $threadID, $threadName,
                              $userID, $userName,
                              $messageContent, $messageID,
                              $botID, $botName): object
    {
        $object = new stdClass();
        $object->serverID = $serverID;
        $object->serverName = $serverName;
        $object->channelID = $channelID;
        $object->channelName = $channelName;
        $object->threadID = $threadID;
        $object->threadName = $threadName;
        $object->userID = $userID;
        $object->userName = $userName;
        $object->messageContent = $messageContent;
        $object->messageID = $messageID;
        $object->botID = $botID;
        $object->botName = $botName;

        $object->placeholderArray = array();
        $object->newLine = DiscordProperties::NEW_LINE;

        $object->planName = $this->plan->name;
        $object->planDescription = $this->plan->description;
        $object->planCreationDate = $this->plan->creationDate;
        $object->planCreationReason = $this->plan->creationReason;
        $object->planExpirationDate = $this->plan->expirationDate;
        $object->planExpirationReason = $this->plan->expirationReason;
        return $object;
    }

    private function getPublic($userID, ?int $limit = 0): array
    {
        $cacheKey = array(__METHOD__, $this->plan->applicationID, $this->plan->planID, $userID, $limit);
        $cache = get_key_value_pair($cacheKey);

        if ($cache !== null) {
            return $cache;
        } else {
            $times = array();
            $array = get_sql_query(
                BotDatabaseTable::BOT_PUBLIC_INSTRUCTIONS,
                null,
                array(
                    array("deletion_date", null),
                    array("application_id", $this->plan->applicationID),
                    null,
                    array("user_id", "IS", null, 0),
                    array("user_id", $userID),
                    null,
                    null,
                    array("plan_id", "IS", null, 0),
                    array("plan_id", "=", $this->plan->planID, 0),
                    $this->plan->family !== null ? array("family", $this->plan->family) : "",
                    null
                ),
                "plan_id ASC, priority DESC",
                $limit
            );

            if (!empty($array)) {
                foreach ($array as $arrayKey => $row) {
                    $times[strtotime(get_future_date($row->information_duration))] = $row->information_duration;

                    if ($row->information_expiration !== null
                        && $row->information_expiration > get_current_date()) {
                        $array[$arrayKey] = $row->information_value;
                    } else {
                        $doc = get_domain_from_url($row->information_url) == "docs.google.com"
                            ? get_raw_google_doc($row->information_url) :
                            timed_file_get_contents($row->information_url);

                        if ($doc !== null) {
                            $array[$arrayKey] = $doc;
                            set_sql_query(
                                BotDatabaseTable::BOT_PUBLIC_INSTRUCTIONS,
                                array(
                                    "information_value" => $doc,
                                    "information_expiration" => get_future_date($row->information_duration)
                                ),
                                array(
                                    array("id", $row->id)
                                )
                            );
                        } else {
                            var_dump("Failed to retrieve value for: " . $row->information_url);

                            if ($row->information_value !== null) {
                                $array[$arrayKey] = $row->information_value;
                                var_dump("Used backup value for: " . $row->information_url);
                            } else {
                                unset($array[$arrayKey]);
                            }
                        }
                    }
                }

                if (empty($times)) {
                    $times[] = "1 minute";
                } else {
                    sort($times);
                }
                set_key_value_pair($cacheKey, $array, $times[0]);
            }
            return $array;
        }
    }
}