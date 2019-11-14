from PIL import Image
from PIL import ImageFile
ImageFile.LOAD_TRUNCATED_IMAGES = True
import os
import sys
import glob
import json
import shutil

def px_negative(pixel):
    r, g, b = pixel
    return (255 - r, 255 - g, 255 - b)


def px_white_black(pixel, brightness):
    r, g, b = pixel
    separator = 255 / brightness / 2 * 3
    total = r + g + b
    if total > separator:
        return (255, 255, 255)
    else:
        return (0, 0, 0)


def px_gray_scale(pixel):
    r, g, b = pixel
    gray = int(r * 0.2126 + g * 0.7152 + b * 0.0722)
    return (gray, gray, gray)



# def px_ColorCorrection(pixel, gain, contrast, gainR, gainG, gainB):
def px_ColorCorrection(pixel, gainR, gainG, gainB, intercept):

    r, g, b = pixel

    red = r * gainR + intercept
    green = g * gainG + intercept
    blue = b * gainB + intercept
    # red = min(255, max(0, red))
    # green = min(255, max(0, green))
    # blue = min(255, max(0, blue))

    return (int(red), int(green), int(blue))


def px_sepia(pixel):
    r, g, b = pixel
    red = int(r * 0.393 + g * 0.769 + b * 0.189)
    green = int(r * 0.349 + g * 0.686 + b * 0.168)
    blue = int(r * 0.272 + g * 0.534 + b * 0.131)
    return (red, green, blue)

def px_saturate(pixel, amount):
    r, g, b = pixel

    red = (0.213 + 0.787*amount)*r + (0.715 - 0.715*amount)*g + (0.072 - 0.072*amount)*b
    green = (0.213 - 0.213*amount)*r + (0.715 + 0.285*amount)*g + (0.072 - 0.072*amount)*b
    blue = (0.213 - 0.213*amount)*r + (0.715 - 0.715*amount)*g + (0.072 + 0.928*amount)*b
    # red = min(255, max(0, red))
    # green = min(255, max(0, green))
    # blue = min(255, max(0, blue))

    return (int(red), int(green), int(blue))

def keys_exists(element, *keys):
    '''
    Check if *keys (nested) exists in `element` (dict).
    '''
    if type(element) is not dict:
        raise AttributeError('keys_exists() expects dict as first argument.')
    if len(keys) == 0:
        raise AttributeError('keys_exists() expects at least two arguments, one given.')

    _element = element
    for key in keys:
        if not element.get(key, False):
            return False
    return True
 

def do_filter(resultpixels, args):
    len_argv = len(args)

    f_lev_intercept = 0
    f_lev_gainR = 1
    f_lev_gainG = 1
    f_lev_gainB = 1
    f_sat = 1
    f_wb = 1

    dx = 0
    dy = 0
    dWidth = 0
    dHeight = 0


    dofilters = False

    i = 0
    action = []
    while i < len_argv:
        action.append(0)
        if args[i] == 'region':
            dx = int(args[i+1])
            dy = int(args[i+2])
            dWidth = int(args[i+3])+dx
            dHeight = int(args[i+4])+dy

        if args[i] == 'levels' or args[i] == 'lev':
            dofilters = True
            action[i] = 1
            f_lev_intercept = 128 * (1 - float(args[i+2]));
            f_lev_gainR = float(args[i+1]) * float(args[i+3]) * float(args[i+2]);
            f_lev_gainG = float(args[i+1]) * float(args[i+4]) * float(args[i+2]);
            f_lev_gainB = float(args[i+1]) * float(args[i+5]) * float(args[i+2]);

        if args[i] == 'sepia' or args[i] == 'sep':
            dofilters = True
            action[i] = 2
        if args[i] == 'saturate' or args[i] == 'sat':
            dofilters = True
            action[i] = 3
            f_sat = float(args[i+1]);
        if args[i] == 'negative' or args[i] == 'neg':
            dofilters = True
            action[i] = 4
        if args[i] == 'white_black' or args[i] == 'wb':
            dofilters = True
            action[i] = 5
            f_wb = float(args[i+1]);
        if args[i] == 'gray_scale' or args[i] == 'gray':
            dofilters = True
            action[i] = 6

        i+=1

    if not dofilters: return 0


    for x in range(dx, dWidth-1):
        for y in range(dy, dHeight-1):

    #######################################
    #######################################
    #######################################
    #######################################
            try:
                pixel = resultpixels[x,y]
            except IndexError as e:
                print e, x," - ",y
    #######################################
    #######################################
    #######################################
    #######################################
            # pixel = dosomething(r, g, b, ...)
            i = 3
            while i < len_argv:
                if action[i] == 1:
                    pixel = px_ColorCorrection(pixel, f_lev_gainR, f_lev_gainG, f_lev_gainB, f_lev_intercept);
                    i += 6
                    if i>=len_argv: break
                if action[i] == 2:
                    pixel = px_sepia(pixel)
                    i += 1
                    if i>=len_argv: break
                if action[i] == 3:
                    pixel = px_saturate(pixel, f_sat)
                    i += 2
                    if i>=len_argv: break
                if action[i] == 4:
                    pixel = px_negative(pixel)
                    i += 1
                    if i>=len_argv: break
                if action[i] == 5:
                    pixel = px_white_black(pixel, f_wb)
                    i += 2
                    if i>=len_argv: break
                if action[i] == 6:
                    pixel = px_gray_scale(pixel)
                    i += 1
                    if i>=len_argv: break
                i += 1

            resultpixels[x,y] = pixel
    return resultpixels


    return 0

if len(sys.argv)<3:
    print "nothing to do"
    print "USAGE: python batchproc.py path/to/jobs/files path/to/new/destination "
    exit()


# data = {
#     'srcFile': 'orders/~2019_06_17_16_39_12.99.jpg',
#     'dstFile': 'orders/~2019_06_17_16_39_12.99.jpg',
#     'Width': 1500,
#     'Height': 1050,
#     'srcX': 10,
#     'srcY': 10,
#     'srcWidth': 1500,
#     'srcHeight': 1050,
#     'dstX': 10,
#     'dstY': 10,
#     'dstWidth': 1500,
#     'dstHeight': 1050,
#     'brightness': 1.2,
#     'contrast': 1,
#     'saturate': 1,
#     'colorR': 1,
#     'colorG': 1,
#     'colorB': 1,
#     'sepia': False,
# }

folder = sys.argv[1];

files = glob.glob(folder+'/*.job')
for jobfile in files:
    with open(jobfile) as json_file:  
        j = json.load(json_file)

    try:
        #os.remove(jobfile)
        os.rename(jobfile, jobfile+'~')
    except OSError:
        print "can't remove ", j['srcFile']

    filters = [];
    filters.append('region')
    filters.append(j['dstX'])
    filters.append(j['dstY'])
    filters.append(j['dstWidth'])
    filters.append(j['dstHeight'])

    if keys_exists(j, "contrast", "brightness", "colorR", "colorG", "colorB"):
        if j['brightness'] != 1 or j['contrast'] != 1 or j['colorR'] != 1 or j['colorB'] != 1 or j['colorG'] != 1 :
            filters.append('levels')
            filters.append(j['brightness'])
            filters.append(j['contrast'])
            filters.append(j['colorR'])
            filters.append(j['colorG'])
            filters.append(j['colorB'])
    if keys_exists(j, "sepia"):
        if j['sepia']:
            filters.append('sepia')
    if keys_exists(j, "saturate"):
        if j['saturate'] != 1:
            filters.append('saturate')
            filters.append(j['saturate'])
    if keys_exists(j, "negative"):
        if j['negative']:
            filters.append('negative')
    if keys_exists(j, "white_black"):
        if j['white_black'] != 1:
            filters.append('white_black')
            filters.append(j['white_black'])
    if keys_exists(j, "gray_scale"):
        if j['gray_scale']:
            filters.append('gray_scale')

    # print filters

    source = Image.open(j['srcFile'])

    source = source.crop( (j['srcX'] , j['srcY'] , j['srcX']+j['srcWidth'], j['srcY']+j['srcHeight']) )
    # source.save(sys.argv[2]+'/~~~1crop.jpeg', "JPEG")

    if keys_exists(j, "imageRotate"):
        if j['imageRotate']>0:
            # set clockwise to counter clockwise
            if j['imageRotate']==90:
                j['imageRotate']=270
            elif j['imageRotate']==270:
                j['imageRotate']=90
            source = source.rotate(j['imageRotate'], expand=1)
            # source.save(sys.argv[2]+'/~~~2rotate.jpeg', "JPEG")

    source = source.resize( (j['dstWidth'], j['dstHeight']), Image.ANTIALIAS)
    # source.save(sys.argv[2]+'/~~~3resize.jpeg', "JPEG")

    result = Image.new('RGB', (j['Width'],j['Height']) , "white")
    result.paste(source, (j['dstX'],j['dstY']) )
    resultpixels = result.load()

    resultpixels = do_filter(resultpixels, filters)
    #### SAVING in jobsdir
    result.save(j['dstFile'], "JPEG")
    #### SAVING in jobsdir

    #### Move to orderdir
    basename = os.path.basename(j['dstFile'])
    destfile = sys.argv[2]+"/"+basename
    try:
        if keys_exists(j, "count"):
            for x in range(2, j['count']+1):
                namecopy = "_{}.jpg".format(x)
                shutil.copyfile(j['dstFile'], destfile+namecopy) 

        os.rename(j['dstFile'], destfile)

    except OSError:
        print "can't rename ", j['dstFile'], ' to ',destfile
    #### Move to orderdir

    try:
        #os.remove(j['srcFile'])
        os.rename(j['srcFile'], j['srcFile']+'~')
    except OSError:
        print "can't remove ", j['srcFile']


sys.exit(0)

    