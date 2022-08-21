#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/types.h>
#include <sys/socket.h>
#include <sys/un.h>

#include "lo/lo.h"

#include "osc.h"
#include "debug.h"

int done = 0;
lo_server_thread st;

void error(int num, const char *msg, const char *path)
{
    printf("liblo server error %d in path %s: %s\n", num, path, msg);
    fflush(stdout);
}

int status_handler(const char *path, const char *types, lo_arg ** argv,
                int argc, void *data, void *user_data)
{
    printf("deck: %i\n"
            "path: %s\n"
            "artist: %s\n"
            "title: %s\n"
            "length: %f\n"
            "position: %f\n"
            "pitch: %f\n"
            "timecode_control: %i\n"
            "player_sync_pitch: %f\n"
            "timecode: %s\n",
            argv[0]->i,
            &argv[1]->s,
            &argv[2]->s,
            &argv[3]->s,
            argv[4]->f,
            argv[5]->f,
            argv[6]->f,
            argv[7]->i,
            argv[8]->f,
            &argv[9]->s);
    
    done = 1;
    
    return 0;
}

int cmdstate_handler(const char *path, const char *types, lo_arg ** argv,
                int argc, void *data, void *user_data)
{
	if (argv[0]->i == 0) {
        printf("OK\n");
        done = 1;
        return 0;
    }
    printf("ERROR\n");
    done = 1;
    return 1;
}

void osc_start_server()
{
    /* start a new server on port 7770 */
    st = lo_server_thread_new("7771", error);
    
    lo_server_thread_add_method(st, "/xwax/status", "isssfffifs", status_handler, NULL);
    
    lo_server_thread_add_method(st, "/xwax/cmdstate", "i", cmdstate_handler, NULL);
    
    lo_server_thread_start(st);
}

int osc_send_get_status(char *server, int d)
{
    done = 0;
    osc_start_server();
    
    lo_address t = lo_address_new(server, "7770");
    
    if (lo_send(t, "/xwax/get_status", "i", d) == -1) {
        fprintf(stderr, "OSC error %d: %s\n", lo_address_errno(t),
            lo_address_errstr(t));
        return 1;
    }

    while (!done) {
        usleep(1000);
    }

    lo_server_thread_free(st);
    
    return 0;
}

int osc_send_load_track(char *server, int d, char *path, char *artist, char *title)
{
    osc_start_server();
    lo_address t = lo_address_new(server, "7770");
    
    if (lo_send(t, "/xwax/load_track", "isss", d, path, artist, title) == -1) {
        fprintf(stderr, "OSC error %d: %s\n", lo_address_errno(t),
            lo_address_errstr(t));
        return 1;
    }
    
    while (!done) {
        usleep(1000);
    }

    lo_server_thread_free(st);
    
    return 0;
}

int osc_send_set_cue(char *server, int d, int q, double pos)
{
    osc_start_server();
    lo_address t = lo_address_new(server, "7770");
    
    if (lo_send(t, "/xwax/set_cue", "iif", d, q, pos) == -1) {
        fprintf(stderr, "OSC error %d: %s\n", lo_address_errno(t),
            lo_address_errstr(t));
        return 1;
    }
    while (!done) {
        usleep(1000);
    }
    lo_server_thread_free(st);
    
    return 0;
}

int osc_send_position(char *server, int d, double pos)
{
    osc_start_server();
    lo_address t = lo_address_new(server, "7770");
    
    if (lo_send(t, "/xwax/position", "if", d, pos) == -1) {
        fprintf(stderr, "OSC error %d: %s\n", lo_address_errno(t),
            lo_address_errstr(t));
        return 1;
    }
    
    while (!done) {
        usleep(1000);
    }
    lo_server_thread_free(st);
    
    return 0;
}

int osc_send_pitch(char *server, int d, double pitch)
{
    lo_address t = lo_address_new(server, "7770");
    
    if (lo_send(t, "/xwax/pitch", "if", d, pitch) == -1) {
        fprintf(stderr, "OSC error %d: %s\n", lo_address_errno(t),
            lo_address_errstr(t));
        return 1;
    }
    
    return 0;
}

int osc_send_punch_cue(char *server, int d, int q)
{
    osc_start_server();
    lo_address t = lo_address_new(server, "7770");
    
    if (lo_send(t, "/xwax/punch_cue", "ii", d, q) == -1) {
        fprintf(stderr, "OSC error %d: %s\n", lo_address_errno(t),
            lo_address_errstr(t));
        return 1;
    }
    
    while (!done) {
        usleep(1000);
    }
    lo_server_thread_free(st);
    
    return 0;
}

int osc_send_disconnect(char *server, int d)
{

	osc_start_server();

    lo_address t = lo_address_new(server, "7770");
    
    if (lo_send(t, "/xwax/disconnect", "i", d) == -1) {
        fprintf(stderr, "OSC disconnect error %d: %s\n", lo_address_errno(t),
            lo_address_errstr(t));
        return 1;
    }
    
    while (!done) {
        usleep(1000);
    }

    lo_server_thread_free(st);
    
    return 0;
}

int osc_send_reconnect(char *server, int d)
{
    osc_start_server();
    lo_address t = lo_address_new(server, "7770");
    
    if (lo_send(t, "/xwax/reconnect", "i", d) == -1) {
        fprintf(stderr, "OSC reconnect error %d: %s\n", lo_address_errno(t),
            lo_address_errstr(t));
        return 1;
    }
    
    while (!done) {
        usleep(1000);
    }
    lo_server_thread_free(st);
    
    return 0;
}

int osc_send_cycle_timecode(char *server, int d)
{
    osc_start_server();
    lo_address t = lo_address_new(server, "7770");
    
    if (lo_send(t, "/xwax/cycle_timecode", "i", d) == -1) {
        fprintf(stderr, "OSC cycle_timecode error %d: %s\n", lo_address_errno(t),
            lo_address_errstr(t));
        return 1;
    }
    
    while (!done) {
        usleep(1000);
    }
    lo_server_thread_free(st);
    
    return 0;
}

int osc_send_recue(char *server, int d)
{
	osc_start_server();
    lo_address t = lo_address_new(server, "7770");
    
    if (lo_send(t, "/xwax/recue", "i", d) == -1) {
        fprintf(stderr, "OSC recue error %d: %s\n", lo_address_errno(t),
            lo_address_errstr(t));
        return 1;
    }
    
    while (!done) {
        usleep(1000);
    }
    lo_server_thread_free(st);
    
    return 0;
}

static void usage(FILE *fd)
{
    fprintf(fd, "Usage: xwax-client <server> load_track <deck-number> "
            "<pathname> <artist> <title>\n"
            "xwax-client <server> set_cue <deck-number> "
            "<cue-number> <position>\n"
            "xwax-client <server> punch_cue <deck-number> "
            "<cue-number>\n"
            "xwax-client <server> recue <deck-number>\n"
            "xwax-client <server> disconnect <deck-number>\n"
            "xwax-client <server> reconnect <deck-number>\n"
            "xwax-client <server> cycle_timecode <deck-number>\n"
            "xwax-client <server> get_status <deck-number>\n"
            "xwax-client <server> position <deck-number> <position>\n");
}

int main(int argc, char *argv[])
{
    if (argc < 2) {
        usage(stderr);
        return 0;
    }
    
    if(strcmp(argv[2], "load_track") == 0) {
        if (argc < 7) {
            usage(stderr);
            return 0;
        }        
        
        int d;
        char *server;
        char *path;
        char *artist;
        char *title;
        
        server = argv[1];
        d = atoi(argv[3]);
        path = argv[4];
        artist = argv[5];
        title = argv[6];
        
        return osc_send_load_track(server, d, path, artist, title);
        
    }else if(strcmp(argv[2], "set_cue") == 0) {
        if (argc < 6) {
            usage(stderr);
            return 0;
        }        
        
        int d, q;
        char *server;
        double pos;
        
        server = argv[1];
        d = atoi(argv[3]);
        q = atoi(argv[4]);
        pos = atof(argv[5]);
        
        return osc_send_set_cue(server, d, q, pos);        
        
    }else if(strcmp(argv[2], "punch_cue") == 0) {
        if (argc < 5) {
            usage(stderr);
            return 0;
        }        
        
        int d, q;
        char *server;
                
        server = argv[1];
        d = atoi(argv[3]);
        q = atoi(argv[4]);
        
        return osc_send_punch_cue(server, d, q);        
        
    }else if(strcmp(argv[2], "get_status") == 0) {
        if (argc < 4) {
            usage(stderr);
            return 0;
        }        
        
        int d;
        char *server;
                
        server = argv[1];
        d = atoi(argv[3]);
        
        return osc_send_get_status(server, d);        
    }else if(strcmp(argv[2], "disconnect") == 0) {
        if (argc < 4) {
            usage(stderr);
            return 0;
        }        
        
        int d;
        char *server;
                
        server = argv[1];
        d = atoi(argv[3]);
        return osc_send_disconnect(server, d);        
    }else if(strcmp(argv[2], "reconnect") == 0) {
        if (argc < 4) {
            usage(stderr);
            return 0;
        }        
        
        int d;
        char *server;
                
        server = argv[1];
        d = atoi(argv[3]);
        return osc_send_reconnect(server, d);
    }else if(strcmp(argv[2], "cycle_timecode") == 0) {
        if (argc < 4) {
            usage(stderr);
            return 0;
        }
        
        int d;
        char *server;
        
        server = argv[1];
        d = atoi(argv[3]);
        return osc_send_cycle_timecode(server, d);
    }else if(strcmp(argv[2], "recue") == 0) {
        if (argc < 4) {
            usage(stderr);
            return 0;
        }        
        
        int d;
        char *server;
                
        server = argv[1];
        d = atoi(argv[3]);
        return osc_send_recue(server, d);        
    }else if(strcmp(argv[2], "position") == 0) {
        if (argc < 5) {
            usage(stderr);
            return 0;
        }        
        
        int d;
        double pos;
        char *server;
                
        server = argv[1];
        d = atoi(argv[3]);
        pos = atof(argv[4]);
        return osc_send_position(server, d, pos);        
    }else if(strcmp(argv[2], "pitch") == 0) {
        if (argc < 5) {
            usage(stderr);
            return 0;
        }        
        
        int d;
        double pitch;
        char *server;
                
        server = argv[1];
        d = atoi(argv[3]);
        pitch = atof(argv[4]);
        return osc_send_pitch(server, d, pitch);        
    }else {
        usage(stderr);
        return 0;
    }
        
    return 0;

}
