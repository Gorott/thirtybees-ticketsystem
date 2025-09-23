<?php

namespace TicketSystem\Enums;

enum MessageAuthor: int
{
    case EMPLOYEE = 1;
    case CUSTOMER = 2;
    case GUEST = 3;
}
