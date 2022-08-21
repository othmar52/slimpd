/*
 * Copyright (C) 2012 Mark Hills <mark@pogo.org.uk>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2, as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * General Public License version 2 for more details.
 *
 * You should have received a copy of the GNU General Public License
 * version 2 along with this program; if not, write to the Free
 * Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 *
 */


/*
 *  Copyright (C) 2004 Steve Harris, Uwe Koloska
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU Lesser General Public License as
 *  published by the Free Software Foundation; either version 2.1 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Lesser General Public License for more details.
 *
 *  $Id$
 */

#define _GNU_SOURCE /* strdupa() */

#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <string.h>

#include "osc.h"
#include "player.h"
#include "deck.h"
#include "track.h"
#include "cues.h"
#include "player.h"

#include "lo/lo.h"

int done = 0;

lo_server_thread st;
lo_server_thread st_tcp;
pthread_t thread_osc_updater;
struct deck *osc_deck;
struct library *osc_library;
int osc_ndeck = 0;
lo_address address[2];
int osc_nconnection = 0;
int osc_nclient = 0;



void osc_add_deck()
{
    ++osc_ndeck;
    fprintf(stderr, "osc.c: osc_add_deck(): osc_ndeck: %i\n", osc_ndeck);
}

void osc_start_updater_thread()
{
    pthread_create( &thread_osc_updater, NULL, osc_start_updater, (void*) NULL);
    
    pthread_setschedprio(thread_osc_updater, 80);
}

void osc_start_updater()
{
    while(!done) {
        int i;
        for(i = 0; i < osc_ndeck; ++i) {
            osc_send_pos(i, player_get_elapsed(&osc_deck[i].player), osc_deck[i].player.pitch);
        }
        // wierd. much less jitter when sleeping between updates
        usleep(1000);
    }
}

int osc_send_ppm_block(struct track *tr)
{
    if(tr) {
        int c;
        for(c = 0; c < osc_nclient%3; ++c) {
            int i = 0;
            while(i < tr->length) {
                int j = 0;
                unsigned char ppm_block[24575];
                
                while(j < 24575 && i < tr->length) {
                    ppm_block[j] = track_get_ppm(tr, i);
                    ++j;
                    i += 64;
                }
                
                /* build a blob object from some data */
                lo_blob blob = lo_blob_new(j, &ppm_block);

                //lo_server server = lo_server_thread_get_server(st_tcp);
                if (lo_send(address[c], "/xwax/ppm", "ibi", 
                    (int) tr, 
                    blob,
                    tr->length
                ) == -1) {
                    printf("OSC error %d: %s\n", lo_address_errno(address[c]),
                        lo_address_errstr(address[c]));
                }
                lo_blob_free(blob);
            }
            lo_send(address[c], "/xwax/ppm_end", "i", (int) tr);
            
            printf("Sent %p blocks to %s\n", tr, lo_address_get_url(address[c]));
            sleep(1); // Wierd bug in liblo that makes second track load not catched by client's track_load_handler if sent too fast
        }

    }
    return 0;
}

int osc_send_pos(int d, const float pos, const float pitch)
{
    //lo_address t = lo_address_new(NULL, "7771");
    //lo_address t = lo_address_new(address, "7771");

    /* send a message to /xwax/position with one float argument, report any
     * errors */
    int c;
    for(c = 0; c < osc_nclient%3; ++c) {
        if (lo_send(address[c], "/xwax/position", "iff", d, pos, pitch) == -1) {
            printf("OSC error %d: %s\n", lo_address_errno(address[c]),
                   lo_address_errstr(address[c]));
        }
    }
        
    return 0;
}

int osc_send_track_load(struct deck *de)
{
    struct player *pl;
    struct track *tr;
    pl = &de->player;
    tr = pl->track;
    
    if(tr) {
        /* send a message to /xwax/track_load with two arguments, report any
         * errors */
        int c;
        for(c = 0; c < osc_nclient%3; ++c) { 
            if (lo_send(address[c], "/xwax/track_load", "iissi",
                    de->ncontrol,
                    (int) tr,
                    de->record->artist, 
                    de->record->title,
                    tr->rate
                ) == -1) {
                printf("OSC error %d: %s\n", lo_address_errno(address[c]),
                       lo_address_errstr(address[c]));
            }
            printf("osc_send_track_load: sent track_load to %s\n", lo_address_get_url(address[c]));
            sleep(1); // Wierd bug in liblo that makes second track load not catched by client's track_load_handler if sent too fast
            
        }
    }
    
    return 0;
}

int osc_send_scale(int scale)
{
    /* send a message to /xwax/track_load with two arguments, report any
     * errors */
    int c;
    for(c = 0; c < osc_nclient%3; ++c) {
        if (lo_send(address[c], "/xwax/scale", "i", scale) == -1) {
            printf("OSC error %d: %s\n", lo_address_errno(address[c]),
                   lo_address_errstr(address[c]));
        }
    }
    
    return 0;
}

int osc_start(struct deck *deck, struct library *library)
{
    osc_deck = deck;
    osc_library = library;
    
    address[0] = lo_address_new_from_url("osc.udp://0.0.0.0:7771/");
    address[1] = lo_address_new_from_url("osc.udp://0.0.0.0:7771/");
    
    
    /* start a new server on port 7770 */
    st = lo_server_thread_new("7770", error);
    
    lo_server_thread_add_method(st, "/xwax/load_track", "isss", load_track_handler, NULL);

    lo_server_thread_add_method(st, "/xwax/set_cue", "iif", set_cue_handler, NULL);

    lo_server_thread_add_method(st, "/xwax/punch_cue", "ii", punch_cue_handler, NULL);  

    lo_server_thread_add_method(st, "/xwax/get_status", "i", get_status_handler, NULL);
    
    lo_server_thread_add_method(st, "/xwax/recue", "i", recue_handler, NULL); 
  
    lo_server_thread_add_method(st, "/xwax/disconnect", "i", disconnect_handler, NULL);    
    
    lo_server_thread_add_method(st, "/xwax/reconnect", "i", reconnect_handler, NULL);
    
    lo_server_thread_add_method(st, "/xwax/cycle_timecode", "i", cycle_timecode_handler, NULL);

    lo_server_thread_add_method(st, "/xwax/connect", "", connect_handler, NULL);

    lo_server_thread_add_method(st, "/xwax/pitch", "if", pitch_handler, NULL);

    lo_server_thread_add_method(st, "/xwax/position", "if", position_handler, NULL);
    
    /* add method that will match the path /quit with no args */
    lo_server_thread_add_method(st, "/quit", "", quit_handler, NULL);

    lo_server_thread_start(st);
    
    ///* Start a TCP server used for receiving PPM packets */
    //st_tcp = lo_server_thread_new_with_proto("7771", LO_TCP, error);
    //if (!st_tcp) {
        //printf("Could not create tcp server thread.\n");
        //exit(1);
    //}
    
    //lo_server s = lo_server_thread_get_server(st);   
    
    ///* add method that will match the path /foo/bar, with two numbers, coerced
     //* to float and int */
    
    //lo_server_thread_start(st_tcp);
    
    //printf("Listening on TCP port %s\n", "7771");

    return 0;
}

void osc_stop()
{
    done = 1;
    lo_server_thread_free(st);
}

void error(int num, const char *msg, const char *path)
{
    printf("liblo server error %d in path %s: %s\n", num, path, msg);
    fflush(stdout);
}

/* catch any incoming messages and display them. returning 1 means that the
 * message has not been fully handled and the server should try other methods */
int generic_handler(const char *path, const char *types, lo_arg ** argv,
                    int argc, void *data, void *user_data)
{
    int i;

    printf("Unsupported OSC message. path: <%s>\n", path);
    for (i = 0; i < argc; i++) {
        printf("arg %d '%c' ", i, types[i]);
        lo_arg_pp((lo_type)types[i], argv[i]);
        printf("\n");
    }
    printf("\n");
    fflush(stdout);

    return 1;
}

int load_track_handler(const char *path, const char *types, lo_arg ** argv,
                int argc, void *data, void *user_data)
{
    /* example showing pulling the argument values out of the argv array */
    printf("%s <- deck:%i path:%s artist:%s title:%s\n", path, argv[0]->i, &argv[1]->s, &argv[2]->s, &argv[3]->s);
    fflush(stdout);
    
    int d;
    struct deck *de;
    struct record *r;    
    
    d = argv[0]->i;
    de = &osc_deck[d];

    r = malloc(sizeof *r);
    if (r == NULL) {
        perror("malloc");
        return -1;
    }
    
    r->pathname = strdup(&argv[1]->s);
    r->artist = strdup(&argv[2]->s);
    r->title = strdup(&argv[3]->s);  

    r = library_add(osc_library, r);
    if (r == NULL) {
        /* FIXME: memory leak, need to do record_clear(r) */
        return -1;
    }

    deck_load(&osc_deck[d], r);
    
    /* send OK to xwax-client. TODO: check if deck really exists */
    osc_send_ok(lo_message_get_source(data));

    return 0;
}

int set_cue_handler(const char *path, const char *types, lo_arg ** argv,
                int argc, void *data, void *user_data)
{
    /* example showing pulling the argument values out of the argv array */
    printf("%s <- deck:%i cue:%i position:%f\n", path, argv[0]->i, argv[1]->i, argv[2]->f);
    fflush(stdout);
    
    int d, q;
    double pos;
    struct deck *de;
    
    d = argv[0]->i;
    q = argv[1]->i;
    pos = argv[2]->f;
    
    de = &osc_deck[d];
    
    cues_set(&de->cues, q, pos);
    
    /* send OK to xwax-client. TODO: check if deck really exists */
    osc_send_ok(lo_message_get_source(data));
    
    return 0;
}

int punch_cue_handler(const char *path, const char *types, lo_arg ** argv,
                int argc, void *data, void *user_data)
{
    /* example showing pulling the argument values out of the argv array */
    printf("%s <- deck:%i cue:%i\n", path, argv[0]->i, argv[1]->i);
    fflush(stdout);
    
    int d, q;
    struct deck *de;
    
    d = argv[0]->i;
    q = argv[1]->i;
    
    de = &osc_deck[d];
    
    deck_cue(de, q);
    
    /* send OK to xwax-client. TODO: check if deck really exists */
    osc_send_ok(lo_message_get_source(data));
    
    return 0;
}

int get_status_handler(const char *path, const char *types, lo_arg ** argv,
                int argc, void *data, void *user_data)
{
    /* example showing pulling the argument values out of the argv array */
    printf("%s <- deck:%i\n", path, argv[0]->i);
    fflush(stdout);
    
    int d = argv[0]->i;
    
    lo_address a = lo_message_get_source(data);
    
    char* url = lo_address_get_url(a);
    
    printf("%s\n", url);
    
    //url[strlen(url)-2] = 1;
    
    //printf("%s\n", url);
    
    osc_send_ok(lo_message_get_source(data));
    osc_send_status(a, d);
    
    return 0;
}

int osc_send_ok(lo_address a)
{
    /* send a message to /xwax/cmdstate */
    if (lo_send(a, "/xwax/cmdstate", "i", 0) == -1) {
        printf("OSC error %d: %s\n", lo_address_errno(a),
               lo_address_errstr(a));
    }
    
    return 0;
}

int osc_send_error(lo_address a, int d)
{
    /* send a message to /xwax/cmdstate */
    if (lo_send(a, "/xwax/cmdstate", "i", 1) == -1) {
        printf("OSC error %d: %s\n", lo_address_errno(a),
               lo_address_errstr(a));
    }
    printf("osc_send_error\n");
    
    return 0;
}

int osc_send_status(lo_address a, int d)
{
    struct deck *de;
    struct player *pl;
    struct track *tr;
    de = &osc_deck[d];
    pl = &de->player;
    tr = pl->track;
    
    char *path;
    if(tr->path)
        path = tr->path;
    else
        path = "";
    
    if(tr) {
        /* send a message to /xwax/status */
        if (lo_send(a, "/xwax/status", "isssfffifs",
                de->ncontrol,           // deck number (int)
                path,               // track path (string)
                de->record->artist,     // artist name (string)
                de->record->title,      // track title (string)
                (float) tr->length / (float) tr->rate,  // track length in seconds (float)
                player_get_elapsed(pl),           // player position in seconds (float)
                pl->pitch,              // player pitch (float)
                pl->timecode_control,    // timecode activated or not (int)
                pl->sync_pitch,			// should indicate current timecode input
                pl->timecoder->def->name) // active timecode
            == -1) {
            printf("OSC error %d: %s\n", lo_address_errno(a),
                   lo_address_errstr(a));
        }
    }
    
    return 0;    
}

int pitch_handler(const char *path, const char *types, lo_arg ** argv,
                int argc, void *data, void *user_data)
{
    /* example showing pulling the argument values out of the argv array */
    printf("%s <- f:%f\n", path, argv[1]->f);
    fflush(stdout);
    
    struct deck *de;
    struct player *pl;
    de = &osc_deck[argv[0]->i];
    pl = &de->player;
    
    player_set_pitch(pl, argv[1]->f);

    return 0;
}

int disconnect_handler(const char *path, const char *types, lo_arg ** argv,
                int argc, void *data, void *user_data)
{
    /* example showing pulling the argument values out of the argv array */
    printf("%s <- deck:%i\n", path, argv[0]->i);
    fflush(stdout);
    
    struct deck *de;
    struct player *pl;
    de = &osc_deck[argv[0]->i];
    pl = &de->player;
    pl->timecode_control = false;
    
    /* send OK to xwax-client. TODO: check if deck really exists */
    osc_send_ok(lo_message_get_source(data));
    
    return 0;
}

int reconnect_handler(const char *path, const char *types, lo_arg ** argv,
                int argc, void *data, void *user_data)
{
    /* example showing pulling the argument values out of the argv array */
    printf("%s <- deck:%i\n", path, argv[0]->i);
    fflush(stdout);
    
    struct deck *de;
    struct player *pl;
    de = &osc_deck[argv[0]->i];
    pl = &de->player;
    pl->timecode_control = true;
    
    /* send OK to xwax-client. TODO: check if deck really exists */
    osc_send_ok(lo_message_get_source(data));
    
    return 0;
}

int cycle_timecode_handler(const char *path, const char *types, lo_arg ** argv,
                int argc, void *data, void *user_data)
{
    /* example showing pulling the argument values out of the argv array */
    printf("%s <- deck:%i\n", path, argv[0]->i);
    fflush(stdout);
    
    struct deck *de;
    struct timecoder *tc;
    de = &osc_deck[argv[0]->i];
    tc = &de->timecoder;
    timecoder_cycle_definition(tc);
    
    /* send OK to xwax-client. TODO: check if deck really exists */
    osc_send_ok(lo_message_get_source(data));
    
    return 0;
}

int recue_handler(const char *path, const char *types, lo_arg ** argv,
                int argc, void *data, void *user_data)
{
    /* example showing pulling the argument values out of the argv array */
    printf("%s <- deck:%i\n", path, argv[0]->i);
    fflush(stdout);
    
    struct deck *de;
    de = &osc_deck[argv[0]->i];
    
    deck_recue(de);
    
    /* send OK to xwax-client. TODO: check if deck really exists */
    osc_send_ok(lo_message_get_source(data));

    return 0;
}

int connect_handler(const char *path, const char *types, lo_arg ** argv,
                int argc, void *data, void *user_data)
{
    /* example showing pulling the argument values out of the argv array */
    printf("%s\n", path);
    fflush(stdout);
    
    lo_address a = lo_message_get_source(data);
    
    if(strcmp(lo_address_get_url(address[0]), lo_address_get_url(a)) == 0 || 
        strcmp(lo_address_get_url(address[1]), lo_address_get_url(a)) == 0) {
        // already stored as a client
    } else {
        address[osc_nconnection%2] = lo_address_new_from_url(lo_address_get_url(a));
        printf("OSC client %i address changed to:%s\n", osc_nconnection%2, lo_address_get_url(address[osc_nconnection%2]));
        
        
        ++osc_nconnection;
        if(osc_nclient < 2)
            ++osc_nclient;
    }

    struct deck *de;
    struct player *pl;

    fprintf(stderr, "osc_nclient %i osc_ndeck: %i\n", osc_nclient, osc_ndeck);
    int d;
    for(d = 0; d < osc_ndeck; ++d) {
        de = &osc_deck[d];
        pl = &de->player;
        osc_send_track_load(de);
        osc_send_ppm_block(pl->track);
    }
    
    return 0;
}

int position_handler(const char *path, const char *types, lo_arg ** argv,
                int argc, void *data, void *user_data)
{
    /* example showing pulling the argument values out of the argv array */
    //printf("%s\n", path);
    //fflush(stdout);
    
    struct deck *de;
    struct player *pl;
    de = &osc_deck[argv[0]->i];
    pl = &de->player;
    
    player_seek_to(pl, argv[1]->f);
    
    /* send OK to xwax-client. TODO: check if deck really exists */
    osc_send_ok(lo_message_get_source(data));
    
    return 0;
}

int quit_handler(const char *path, const char *types, lo_arg ** argv,
                 int argc, void *data, void *user_data)
{
    done = 1;
    printf("quiting\n\n");
    fflush(stdout);

    return 0;
}
