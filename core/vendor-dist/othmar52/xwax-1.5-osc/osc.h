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
 * Functions for external control via a socket
 */

#ifndef OSC_H
#define OSC_H

#include <poll.h>
#include <stdlib.h>

#include <lo/lo.h>

#include "list.h"
#include "library.h"
#include "deck.h"

void error(int num, const char *m, const char *path);

int generic_handler(const char *path, const char *types, lo_arg ** argv,
                    int argc, void *data, void *user_data);
int load_track_handler(const char *path, const char *types, lo_arg ** argv,
                int argc, void *data, void *user_data);                      
int set_cue_handler(const char *path, const char *types, lo_arg ** argv,
                int argc, void *data, void *user_data);       
int punch_cue_handler(const char *path, const char *types, lo_arg ** argv,
                int argc, void *data, void *user_data); 
int get_status_handler(const char *path, const char *types, lo_arg ** argv,
                int argc, void *data, void *user_data);    
int pitch_handler(const char *path, const char *types, lo_arg ** argv,
                int argc, void *data, void *user_data);
int recue_handler(const char *path, const char *types, lo_arg ** argv,
                int argc, void *data, void *user_data); 
int disconnect_handler(const char *path, const char *types, lo_arg ** argv,
                int argc, void *data, void *user_data); 
int reconnect_handler(const char *path, const char *types, lo_arg ** argv,
                int argc, void *data, void *user_data);
int cycle_timecode_handler(const char *path, const char *types, lo_arg ** argv,
                int argc, void *data, void *user_data);
int connect_handler(const char *path, const char *types, lo_arg ** argv,
                int argc, void *data, void *user_data);                
int position_handler(const char *path, const char *types, lo_arg ** argv,
                int argc, void *data, void *user_data);  
int quit_handler(const char *path, const char *types, lo_arg ** argv,
                 int argc, void *data, void *user_data);

int osc_start(struct deck *deck, struct library *library);
void osc_stop();
void osc_add_deck();

int osc_send_pos(int d, const float pos, const float pitch);
int osc_send_track_load(struct deck *de);
int osc_send_ppm_block(struct track *tr);
int osc_send_scale(int scale);
int osc_send_status(lo_address a, int d);
int osc_send_error(lo_address a, int d);
int osc_send_ok(lo_address a);

void osc_start_updater_thread();
void osc_start_updater();

#endif
