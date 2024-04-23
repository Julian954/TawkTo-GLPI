# How it Works
This code take the **Webhook** from _tawkto_ and send the data through _glpi's_ api to create tickets in _GLPI_ at the same time as the creation of the ticket in _tawkto_.

You need to have the **WebHook** key From _TawkTo_, you can get it in the settings section and **Webhook**.
Also you need the data from the _glpi_ api like "***User-Token, App-Token, API-URL***" without this you cant connect both systems.

You have to configurate the **Webhook** as a new ticket with this you shouldn't have problems creating tickets in both systems 
obviously only works from _TawkTo_ to _GLPI_ and dont from _GLPI_ to _TawkTo_.
