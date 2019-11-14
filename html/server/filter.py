import sys

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

 

if len(sys.argv)<4:
    print "nothing to do"
    print "USAGE: python filter.py source destination doit [region x y width height]"
    print "region x y width height - set region of destination to apply filters"
    print "doit list:"
    print " level bright contrast r g b"
    print " lev bright contrast r g b"
    print " sepia"
    print " sep"
    print " saturate"
    print " sat"
    print " negative"
    print " neg"
    print " white_black"
    print " wb"
    print " gray_scale"
    print " gray"
    print "SAMPLES:"
    print "python filter.py 1.jpg 2.jpg wb neg "
    print "python filter.py tosepia.jpg tosepia.jpg sep"
    print "python filter.py test.jpg test.jpg lev 1.1 1 1 1 1.2 "
    exit()

def main(args):
    from PIL import Image
    from PIL import ImageFile
    ImageFile.LOAD_TRUNCATED_IMAGES = True
    
    len_argv = len(args)

    intercept = 0;
    gainR = 1;
    gainG = 1;
    gainB = 1;

    source_name = args[1]
    result_name = args[2]
    source = Image.open(source_name)
    sourcepixels = source.load()
    result = Image.new('RGB', source.size, "white")
    resultpixels = result.load()
    dx = 0
    dy = 0
    dWidth = source.size[0]
    dHeight = source.size[1]

    dofilters = False

    i = 3
    action = [0, 0, 0]
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
            intercept = 128 * (1 - float(args[i+2]));
            gainR = float(args[i+1]) * float(args[i+3]) * float(args[i+2]);
            gainG = float(args[i+1]) * float(args[i+4]) * float(args[i+2]);
            gainB = float(args[i+1]) * float(args[i+5]) * float(args[i+2]);
            # pixel = px_ColorCorrection(pixel, float(sys.argv[i+1]), float(sys.argv[i+2]), float(sys.argv[i+3]), float(sys.argv[i+4]), float(sys.argv[i+5]))

        if args[i] == 'sepia' or args[i] == 'sep':
            dofilters = True
            action[i] = 2
        if args[i] == 'saturate' or args[i] == 'sat':
            dofilters = True
            action[i] = 3
        if args[i] == 'negative' or args[i] == 'neg':
            dofilters = True
            action[i] = 4
        if args[i] == 'white_black' or args[i] == 'wb':
            dofilters = True
            action[i] = 5
        if args[i] == 'gray_scale' or args[i] == 'gray':
            dofilters = True
            action[i] = 6

        i+=1

    if not dofilters: return 0


    for x in range(dx, dWidth-1):
        for y in range(dy, dHeight-1):
            # if x<dx or x>dWidth or y<dy or y>dHeight: continue

            # pixel = source.getpixel((x, y))
            pixel = sourcepixels[x,y]
            # do something with r, g, b
            # pixel = dosomething(r, g, b, ...)
            i = 3
            while i < len_argv:
                if action[i] == 1:
                    pixel = px_ColorCorrection(pixel, gainR, gainG, gainB, intercept);
                    i += 6
                    if i>=len_argv: break
                if action[i] == 2:
                    pixel = px_sepia(pixel)
                    i += 1
                    if i>=len_argv: break
                if action[i] == 3:
                    pixel = px_saturate(pixel, float(args[i+1]))
                    i += 2
                    if i>=len_argv: break
                if action[i] == 4:
                    pixel = px_negative(pixel)
                    i += 1
                    if i>=len_argv: break
                if action[i] == 5:
                    pixel = px_white_black(pixel, float(args[i+1]))
                    i += 2
                    if i>=len_argv: break
                if action[i] == 6:
                    pixel = px_gray_scale(pixel)
                    i += 1
                    if i>=len_argv: break
                i += 1

            # result.putpixel((x, y), pixel)
            resultpixels[x,y] = pixel
    result.save(result_name, "JPEG")


    return 0

main(sys.argv)
sys.exit(0)

    